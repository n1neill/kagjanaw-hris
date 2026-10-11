<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceLogStatus;
use App\Enums\AttendanceSource;
use App\Enums\SyncStatus;
use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\DeviceSyncRun;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pulls punches from biometric devices into attendance_logs and refreshes
 * the affected Daily Time Records.
 *
 * Synchronization is idempotent: each run re-reads a window that overlaps the
 * previous successful run, and duplicates are skipped by the unique index on
 * (biometric_user_id, logged_at). Punches from IDs not assigned to any
 * employee, or stamped in the future (a wrong device clock), are stored but
 * flagged and left out of the DTR.
 */
class AttendanceSyncService
{
    /**
     * Punches stamped more than this many minutes ahead of the server clock are flagged.
     */
    private const FUTURE_TOLERANCE_MINUTES = 5;

    public function __construct(
        private BiometricDriverManager $drivers,
        private AttendanceProcessor $processor,
    ) {}

    /**
     * Run one synchronization. Failures are recorded on the run, not thrown.
     */
    public function sync(BiometricDevice $device, ?User $triggeredBy = null, ?CarbonImmutable $since = null): DeviceSyncRun
    {
        $until = CarbonImmutable::now();
        $since ??= $this->defaultSince($device, $until);

        $run = $device->syncRuns()->create([
            'triggered_by' => $triggeredBy?->id,
            'status' => SyncStatus::Running,
            'started_at' => $until,
        ]);

        try {
            $punches = collect($this->drivers->driverFor($device)->fetchLogs($device, $since, $until));

            $counts = $this->store($punches, AttendanceSource::Device, $device);

            $run->fill([
                'status' => SyncStatus::Succeeded,
                'fetched_count' => $punches->count(),
                'inserted_count' => $counts['inserted'],
                'duplicate_count' => $punches->count() - $counts['inserted'],
                'flagged_count' => $counts['flagged'],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $run->fill([
                'status' => SyncStatus::Failed,
                'error_message' => $exception instanceof DeviceConnectionException
                    ? $exception->getMessage()
                    : 'Unexpected error: '.$exception->getMessage(),
            ]);
        }

        $run->finished_at = CarbonImmutable::now();
        $run->save();

        $device->forceFill([
            'last_sync_status' => $run->status,
            'last_synced_at' => $run->status === SyncStatus::Succeeded ? $run->started_at : $device->last_synced_at,
        ])->save();

        return $run;
    }

    /**
     * Save punches, skipping ones already stored, then refresh the affected DTR days.
     *
     * @param  iterable<RawPunch>  $punches
     * @return array{inserted: int, flagged: int}
     */
    public function store(iterable $punches, AttendanceSource $source, ?BiometricDevice $device = null): array
    {
        $punches = collect($punches);

        $employeeIds = Employee::query()
            ->whereIn('biometric_user_id', $punches->pluck('biometricUserId')->unique())
            ->pluck('id', 'biometric_user_id');

        $latestAllowed = CarbonImmutable::now()->addMinutes(self::FUTURE_TOLERANCE_MINUTES);
        $timestamp = CarbonImmutable::now();

        $rows = $punches->map(function (RawPunch $punch) use ($employeeIds, $latestAllowed, $device, $source, $timestamp): array {
            $employeeId = $employeeIds->get($punch->biometricUserId);

            return [
                'biometric_device_id' => $device?->id,
                'employee_id' => $employeeId,
                'biometric_user_id' => $punch->biometricUserId,
                'logged_at' => $punch->loggedAt->format('Y-m-d H:i:s'),
                'verify_type' => $punch->verifyType->value,
                'source' => $source->value,
                'status' => match (true) {
                    $employeeId === null => AttendanceLogStatus::UnknownUser->value,
                    $punch->loggedAt->greaterThan($latestAllowed) => AttendanceLogStatus::FutureTimestamp->value,
                    default => AttendanceLogStatus::Valid->value,
                },
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        });

        [$valid, $flagged] = $rows->partition(fn (array $row): bool => $row['status'] === AttendanceLogStatus::Valid->value);

        $inserted = $this->insertIgnoringDuplicates($valid->all());
        $insertedFlagged = $this->insertIgnoringDuplicates($flagged->all());

        $this->processor->processDates($this->datesByEmployee($valid->all()));

        return ['inserted' => $inserted + $insertedFlagged, 'flagged' => $insertedFlagged];
    }

    /**
     * Attach earlier unknown-ID punches to an employee once HR assigns them
     * that biometric user ID, and rebuild the affected DTR days.
     *
     * @return int The number of punches linked.
     */
    public function linkUnknownLogs(Employee $employee): int
    {
        if (blank($employee->biometric_user_id)) {
            return 0;
        }

        $logs = AttendanceLog::query()
            ->where('status', AttendanceLogStatus::UnknownUser)
            ->where('biometric_user_id', $employee->biometric_user_id)
            ->get(['id', 'logged_at']);

        if ($logs->isEmpty()) {
            return 0;
        }

        AttendanceLog::query()->whereKey($logs->modelKeys())->update([
            'employee_id' => $employee->id,
            'status' => AttendanceLogStatus::Valid,
        ]);

        $this->processor->processDates([
            $employee->id => $logs->map(fn (AttendanceLog $log): string => $log->logged_at->toDateString())->all(),
        ]);

        return $logs->count();
    }

    private function defaultSince(BiometricDevice $device, CarbonImmutable $until): CarbonImmutable
    {
        $lastSuccess = $device->lastSuccessfulRun()->first();

        return $lastSuccess !== null
            ? CarbonImmutable::parse($lastSuccess->started_at)->subHours((int) config('attendance.sync_overlap_hours'))
            : $until->subDays((int) config('attendance.initial_sync_days'))->startOfDay();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertIgnoringDuplicates(array $rows): int
    {
        $inserted = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            $inserted += DB::table('attendance_logs')->insertOrIgnore($chunk);
        }

        return $inserted;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, list<string>>
     */
    private function datesByEmployee(array $rows): array
    {
        return collect($rows)
            ->groupBy('employee_id')
            ->map(fn ($employeeRows) => $employeeRows->map(fn (array $row): string => substr($row['logged_at'], 0, 10))->unique()->values()->all())
            ->all();
    }
}
