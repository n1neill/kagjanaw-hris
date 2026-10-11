<?php

namespace App\Services\Leave;

use App\Enums\LeaveLedgerEntryType;
use App\Enums\LeaveRequestStatus;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveRequestDay;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Attendance\AttendanceReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Posts each month's leave card entries from the validated DTR:
 *
 *  1. Earned: 1.250 days of vacation and of sick leave, prorated for partial
 *     months of service and reduced for days of absence or leave without pay.
 *  2. Tardiness and undertime: the month's total minutes converted to days and
 *     deducted from vacation leave. What vacation leave cannot cover is
 *     recorded as without pay (for payroll).
 *
 * Posting is safe to repeat. If a DTR is corrected and validated again, only
 * the difference from what was already posted is added.
 */
class LeaveCreditPostingService
{
    public function __construct(private LeaveLedger $ledger) {}

    public function postMonth(Employee $employee, CarbonImmutable $month, ?User $user = null): LeavePostingResult
    {
        $month = $month->startOfMonth();

        if (($reason = $this->blockingReason($employee, $month)) !== null) {
            return LeavePostingResult::skipped($reason);
        }

        $label = $month->format('F Y');
        $period = $month;
        $postedOn = $month->endOfMonth();

        $earned = CscLeaveTables::earnedForMonth($this->serviceDays($employee, $month), $this->daysWithoutPay($employee, $month));
        $tardinessMinutes = (int) $this->dtr($employee, $month)->sum(fn ($record): int => $record->late_minutes + $record->undertime_minutes);
        $tardiness = CscLeaveTables::minutesToDays($tardinessMinutes);

        return DB::transaction(function () use ($employee, $period, $postedOn, $label, $earned, $tardiness, $tardinessMinutes, $user): LeavePostingResult {
            $vacationType = LeaveType::vacation();
            $sickType = LeaveType::sick();
            $changed = false;

            foreach ([$vacationType, $sickType] as $ledgerType) {
                $this->ledger->lockedBalance($employee, $ledgerType);

                $earnedPosted = $this->postedFor($employee, $ledgerType, LeaveLedgerEntryType::Accrual, $period);
                $difference = $earned - $earnedPosted->sum('days');

                if ($difference !== 0) {
                    $this->ledger->post(
                        $employee, $ledgerType, LeaveLedgerEntryType::Accrual, $difference,
                        __('Earned, :month', ['month' => $label]).($earnedPosted->isEmpty() ? '' : ' '.__('(recomputed)')),
                        $postedOn, period: $period, user: $user,
                    );
                    $changed = true;
                }
            }

            $tardinessPosted = $this->postedFor($employee, $vacationType, LeaveLedgerEntryType::Tardiness, $period);
            $alreadyCharged = -$tardinessPosted->sum('days');
            $alreadyWithoutPay = $tardinessPosted->sum('without_pay');

            $vacationAvailable = max(0, $this->ledger->lockedBalance($employee, $vacationType)) + $alreadyCharged;
            $charged = min($tardiness, $vacationAvailable);
            $withoutPay = $tardiness - $charged;

            if ($charged !== $alreadyCharged || $withoutPay !== $alreadyWithoutPay) {
                $this->ledger->post(
                    $employee, $vacationType, LeaveLedgerEntryType::Tardiness, -($charged - $alreadyCharged),
                    __('Tardiness and undertime, :month (:time)', [
                        'month' => $label,
                        'time' => AttendanceReportService::formatMinutes($tardinessMinutes),
                    ]).($tardinessPosted->isEmpty() ? '' : ' '.__('(recomputed)')),
                    $postedOn, period: $period, withoutPay: $withoutPay - $alreadyWithoutPay, user: $user,
                );
                $changed = true;
            }

            return new LeavePostingResult(
                $changed ? LeavePostingResult::POSTED : LeavePostingResult::UP_TO_DATE,
                $changed ? __('Leave credits for :month posted.', ['month' => $label]) : __('Leave credits for :month were already posted.', ['month' => $label]),
                $earned,
                $charged,
                $withoutPay,
            );
        });
    }

    /**
     * Post a month for every employee who earns leave credits. Employees who
     * are not ready (e.g. DTR not yet validated) are skipped.
     *
     * @return Collection<int, array{employee: Employee, result: LeavePostingResult}>
     */
    public function postMonthForAll(CarbonImmutable $month, ?User $user = null): Collection
    {
        return $this->employeesFor($month)
            ->map(fn (Employee $employee): array => ['employee' => $employee, 'result' => $this->postMonth($employee, $month, $user)]);
    }

    /**
     * Employees whose leave card could have entries for the month.
     *
     * @return Collection<int, Employee>
     */
    public function employeesFor(CarbonImmutable $month): Collection
    {
        return Employee::query()
            ->with(['employmentType', 'department'])
            ->whereHas('employmentType', fn ($query) => $query->where('earns_leave_credits', true))
            ->where('date_hired', '<=', $month->endOfMonth()->toDateString())
            ->where(fn ($query) => $query->whereNull('separation_date')->orWhere('separation_date', '>=', $month->startOfMonth()->toDateString()))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Why the month cannot be posted yet, or null when it can.
     */
    public function blockingReason(Employee $employee, CarbonImmutable $month): ?string
    {
        $month = $month->startOfMonth();

        if (! $month->endOfMonth()->lessThan(CarbonImmutable::today())) {
            return __('The month has not ended yet.');
        }

        if (! $employee->earnsLeaveCredits()) {
            return __('This employment type does not earn leave credits.');
        }

        if ($this->serviceDays($employee, $month) === 0) {
            return __('Not in service during the month.');
        }

        $opening = $employee->leaveLedgerEntries()
            ->where('entry_type', LeaveLedgerEntryType::OpeningBalance)
            ->max('entry_date');

        if ($opening !== null && CarbonImmutable::parse($opening)->greaterThanOrEqualTo($month->endOfMonth()->startOfDay())) {
            return __('Already included in the opening balance.');
        }

        if ($opening === null && $employee->date_hired->lessThan($month)) {
            return __('No opening balance has been imported.');
        }

        $records = $this->dtr($employee, $month);
        $expectedDays = $this->daysInServiceDuring($employee, $month);

        if ($records->count() < $expectedDays || $records->contains(fn ($record): bool => $record->validated_at === null)) {
            return __('The DTR for the month is not fully validated.');
        }

        return null;
    }

    /**
     * Calendar days in service during the month, with a full month counted as 30.
     */
    private function serviceDays(Employee $employee, CarbonImmutable $month): int
    {
        $days = $this->daysInServiceDuring($employee, $month);

        return $days === $month->daysInMonth ? CscLeaveTables::DAYS_PER_MONTH : $days;
    }

    private function daysInServiceDuring(Employee $employee, CarbonImmutable $month): int
    {
        $start = CarbonImmutable::parse($employee->date_hired)->max($month->startOfMonth());
        $end = $employee->separation_date === null
            ? $month->endOfMonth()->startOfDay()
            : CarbonImmutable::parse($employee->separation_date)->min($month->endOfMonth()->startOfDay());

        return $end->lessThan($start) ? 0 : (int) $start->diffInDays($end) + 1;
    }

    /**
     * Unexcused absences on the DTR plus approved leave without pay, in thousandths.
     */
    private function daysWithoutPay(Employee $employee, CarbonImmutable $month): int
    {
        $absentMinutes = (int) $this->dtr($employee, $month)->sum('absent_minutes');

        $unpaidLeave = LeaveRequestDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->whereHas('leaveRequest', fn ($query) => $query->where('status', LeaveRequestStatus::Approved))
            ->get(['days', 'days_with_pay'])
            ->sum(fn (LeaveRequestDay $day): int => LeaveDays::toThousandths($day->days) - LeaveDays::toThousandths($day->days_with_pay));

        return CscLeaveTables::minutesToDays($absentMinutes) + $unpaidLeave;
    }

    /**
     * @return Collection<int, DailyTimeRecord>
     */
    private function dtr(Employee $employee, CarbonImmutable $month): Collection
    {
        return $employee->dailyTimeRecords()
            ->whereBetween('work_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->get();
    }

    /**
     * Earlier postings of one kind for the month, in thousandths.
     *
     * @return Collection<int, array{days: int, without_pay: int}>
     */
    private function postedFor(Employee $employee, LeaveType $ledgerType, LeaveLedgerEntryType $entryType, CarbonImmutable $period): Collection
    {
        return $employee->leaveLedgerEntries()
            ->where('leave_type_id', $ledgerType->id)
            ->where('entry_type', $entryType)
            ->whereDate('period', $period->toDateString())
            ->get(['days', 'days_without_pay'])
            ->map(fn (LeaveLedgerEntry $entry): array => [
                'days' => LeaveDays::toThousandths($entry->days),
                'without_pay' => LeaveDays::toThousandths($entry->days_without_pay),
            ]);
    }
}
