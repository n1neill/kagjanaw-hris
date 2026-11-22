<?php

namespace App\Services\Approvals\Handlers;

use App\Enums\AuditEvent;
use App\Models\AttendanceLog;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Services\Approvals\ChangeContext;
use App\Services\Attendance\AttendanceCsvImporter;
use App\Services\Attendance\DtrCorrectionService;
use App\Services\Audit\AuditLogger;
use App\Services\Leave\LeaveCreditPostingService;
use App\Services\Leave\LeavePostingResult;
use Carbon\CarbonImmutable;

/**
 * Applies attendance changes: DTR corrections, month validation and
 * recomputation, punch imports and clearing raw logs.
 */
class AttendanceChanges
{
    public function __construct(
        private DtrCorrectionService $corrections,
        private LeaveCreditPostingService $posting,
        private AttendanceCsvImporter $importer,
        private AuditLogger $audit,
    ) {}

    public function correctDtr(ChangeContext $change): DailyTimeRecord
    {
        return $this->corrections->correct(
            $change->subject(Employee::class),
            CarbonImmutable::createFromFormat('!Y-m-d', $change->input('date')),
            $change->input('punches'),
            $change->input('reason'),
            $change->author,
        );
    }

    /**
     * Confirm the month's finished days, then post the month's leave credits.
     *
     * @return array{count: int, posting: LeavePostingResult}
     */
    public function validateDtrMonth(ChangeContext $change): array
    {
        $employee = $change->subject(Employee::class);
        $month = $this->month($change);

        return [
            'count' => $this->corrections->validateMonth($employee, $month, $change->author),
            'posting' => $this->posting->postMonth($employee, $month, $change->author),
        ];
    }

    public function reprocessDtrMonth(ChangeContext $change): void
    {
        $this->corrections->reprocessMonth($change->subject(Employee::class), $this->month($change));
    }

    /**
     * @return array<string, mixed>
     */
    public function importAttendance(ChangeContext $change): array
    {
        return $this->importer->import($change->file('file')->getRealPath());
    }

    /**
     * Clear the punches matching the filters (every punch when none are set).
     * The DTRs already built from them are left as they are, and one audit
     * entry records who cleared what.
     */
    public function clearAttendanceLogs(ChangeContext $change): int
    {
        $filters = $change->input('filters', []);

        $query = AttendanceLog::query()->matchingFilters($filters);
        $count = (clone $query)->count();
        $oldest = (clone $query)->min('logged_at');
        $newest = (clone $query)->max('logged_at');

        $query->delete();

        $this->audit->record(
            AuditEvent::Deleted,
            __('Cleared :count attendance logs', ['count' => $count]),
            oldValues: [
                'filters' => $filters === [] ? __('none (all logs)') : $filters,
                'count' => $count,
                'earliest_punch' => $oldest,
                'latest_punch' => $newest,
            ],
        );

        return $count;
    }

    private function month(ChangeContext $change): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $change->input('month'))->startOfMonth();
    }
}
