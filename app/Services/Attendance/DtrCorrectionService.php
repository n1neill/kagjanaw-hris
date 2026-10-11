<?php

namespace App\Services\Attendance;

use App\Enums\DtrStatus;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HR's review tools for Daily Time Records: correcting punches (with an
 * audit trail), validating a month, and recomputing a month from raw logs.
 */
class DtrCorrectionService
{
    /**
     * @var list<string>
     */
    public const PUNCH_FIELDS = ['am_in', 'am_out', 'pm_in', 'pm_out'];

    public function __construct(
        private AttendanceProcessor $processor,
        private DtrCalculator $calculator,
    ) {}

    /**
     * Replace a day's punches, recompute its totals and record who changed
     * what and why. Corrected days are no longer recomputed from raw logs and
     * must be validated again.
     *
     * @param  array{am_in: ?string, am_out: ?string, pm_in: ?string, pm_out: ?string}  $times  "HH:MM" or null.
     */
    public function correct(Employee $employee, CarbonImmutable $date, array $times, string $reason, User $user): DailyTimeRecord
    {
        $record = $this->recordFor($employee, $date);
        $before = $this->punchTimes($record);

        $punches = DayPunches::fromTimes($times['am_in'], $times['am_out'], $times['pm_in'], $times['pm_out']);
        $schedule = WorkSchedule::current();

        $result = $this->calculator->calculate(
            $punches,
            $schedule->rules(),
            $this->processor->dayTypeFor($date, $schedule),
            $this->processor->nowMinutesFor($date),
            $this->processor->leaveCoverageFor($employee, $date),
        );

        $after = $punches->toTimes();

        $changes = collect(self::PUNCH_FIELDS)
            ->filter(fn (string $field): bool => $before[$field] !== $after[$field])
            ->mapWithKeys(fn (string $field): array => [$field => ['from' => $before[$field], 'to' => $after[$field]]])
            ->all();

        return DB::transaction(function () use ($record, $after, $result, $changes, $reason, $user): DailyTimeRecord {
            $record->fill([...$after, ...$this->processor->resultAttributes($result), 'is_adjusted' => true]);
            $record->forceFill(['validated_by' => null, 'validated_at' => null])->save();

            $record->adjustments()->create([
                'user_id' => $user->id,
                'changes' => $changes,
                'reason' => $reason,
            ]);

            return $record;
        });
    }

    /**
     * The day's current punches as "HH:MM" (null when missing).
     *
     * @return array{am_in: ?string, am_out: ?string, pm_in: ?string, pm_out: ?string}
     */
    public function punchTimes(DailyTimeRecord $record): array
    {
        return DayPunches::fromTimes($record->am_in, $record->am_out, $record->pm_in, $record->pm_out)->toTimes();
    }

    public function recordFor(Employee $employee, CarbonImmutable $date): DailyTimeRecord
    {
        return $employee->dailyTimeRecords()->whereDate('work_date', $date->toDateString())->first()
            ?? new DailyTimeRecord(['employee_id' => $employee->id, 'work_date' => $date->toDateString()]);
    }

    /**
     * Mark a month's finished days as checked by HR. Days with a missing punch
     * must be corrected first.
     *
     * @return int The number of days validated.
     *
     * @throws ValidationException when a day still has a missing punch.
     */
    public function validateMonth(Employee $employee, CarbonImmutable $month, User $user): int
    {
        $days = $employee->dailyTimeRecords()
            ->whereBetween('work_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()]);

        $missingPunchDates = (clone $days)->where('status', DtrStatus::Incomplete)->orderBy('work_date')->pluck('work_date');

        if ($missingPunchDates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'month' => __('Correct the days with a missing punch before validating: :dates.', [
                    'dates' => $missingPunchDates->map(fn ($date): string => $date->format('M j'))->implode(', '),
                ]),
            ]);
        }

        return $days->whereNull('validated_at')
            ->where('status', '!=', DtrStatus::Pending)
            ->update(['validated_by' => $user->id, 'validated_at' => now()]);
    }

    /**
     * Rebuild a month from raw logs. Corrected days are left as they are.
     */
    public function reprocessMonth(Employee $employee, CarbonImmutable $month): void
    {
        $this->processor->processEmployee($employee, $month->startOfMonth(), $month->endOfMonth());
    }
}
