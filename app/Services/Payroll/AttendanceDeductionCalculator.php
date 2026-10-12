<?php

namespace App\Services\Payroll;

use App\Enums\LeaveLedgerEntryType;
use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequestDay;
use App\Models\LeaveType;
use App\Models\PayrollAttendanceDeduction;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\Leave\LeaveCreditPostingService;
use App\Services\Leave\LeaveDays;
use Carbon\CarbonImmutable;

/**
 * Absences and tardiness without pay, deducted in the first payroll after
 * the month's DTR is validated (the month must also be ready for leave
 * posting, so tardiness has been charged to vacation leave first):
 *
 *  - unexcused absences on the DTR (minutes);
 *  - approved leave without pay (days);
 *  - tardiness and undertime vacation leave could not cover (days).
 *
 * A day is monthly salary ÷ 22 and a minute a day ÷ 480; allowances marked
 * "reduced for days without pay" (e.g. PERA) are reduced the same way. If a
 * month is corrected after it was deducted, the next payroll deducts — or
 * refunds — only the difference.
 */
class AttendanceDeductionCalculator
{
    public const WORKING_DAYS_PER_MONTH = 22;

    public const MINUTES_PER_DAY = 480;

    /**
     * How many months back a payroll looks for months still to deduct.
     */
    public const LOOKBACK_MONTHS = 12;

    public function __construct(
        private PayrollSettings $settings,
        private LeaveCreditPostingService $leavePosting,
        private AllowanceResolver $allowances,
    ) {}

    /**
     * @return array{
     *     months: list<array{month: CarbonImmutable, days_without_pay: int, absent_minutes: int, salary_amount: int, allowance_amount: int, taxable_allowance_amount: int}>,
     *     waiting: list<CarbonImmutable>,
     * } Amounts in centavos are the difference still to deduct; "waiting" lists months not yet ready.
     */
    public function for(Employee $employee, int $monthlySalary, PayrollPeriod $period, PayrollRun $run): array
    {
        $result = ['months' => [], 'waiting' => []];

        if (! $employee->earnsLeaveCredits()) {
            return $result;
        }

        foreach ($this->candidateMonths($employee, $period) as $month) {
            if ($this->leavePosting->blockingReason($employee, $month) !== null) {
                if ($this->leavePosting->isAwaitingDtrValidation($employee, $month)) {
                    $result['waiting'][] = $month;
                }

                continue;
            }

            $daysWithoutPay = $this->daysWithoutPay($employee, $month);
            $absentMinutes = (int) $employee->dailyTimeRecords()
                ->whereBetween('work_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
                ->sum('absent_minutes');

            $salaryAmount = $this->amountFor($monthlySalary, $daysWithoutPay, $absentMinutes);
            $allowanceAmount = 0;
            $taxableAllowanceAmount = 0;

            foreach ($this->allowances->for($employee, $month, $month->endOfMonth()) as $allowance) {
                if ($allowance['type']->reduced_for_unpaid_days) {
                    $reduction = $this->amountFor($allowance['monthly'], $daysWithoutPay, $absentMinutes);
                    $allowanceAmount += $reduction;
                    $taxableAllowanceAmount += $allowance['type']->is_taxable ? $reduction : 0;
                }
            }

            $previous = PayrollAttendanceDeduction::query()
                ->where('employee_id', $employee->id)
                ->whereDate('month', $month->toDateString())
                ->whereHas('payrollItem', fn ($items) => $items->where('payroll_run_id', '!=', $run->id))
                ->get();

            $previousSalary = $previous->sum(fn (PayrollAttendanceDeduction $line): int => Money::toCentavos($line->salary_amount));
            $previousAllowance = $previous->sum(fn (PayrollAttendanceDeduction $line): int => Money::toCentavos($line->allowance_amount));

            if ($salaryAmount === $previousSalary && $allowanceAmount === $previousAllowance) {
                continue;
            }

            $allowanceDifference = $allowanceAmount - $previousAllowance;

            $result['months'][] = [
                'month' => $month,
                'days_without_pay' => $daysWithoutPay,
                'absent_minutes' => $absentMinutes,
                'salary_amount' => $salaryAmount - $previousSalary,
                'allowance_amount' => $allowanceDifference,
                'taxable_allowance_amount' => Money::ratio($allowanceDifference, $taxableAllowanceAmount, max(1, $allowanceAmount)),
            ];
        }

        return $result;
    }

    /**
     * The value of days (thousandths) and minutes without pay of a monthly amount.
     */
    public function amountFor(int $monthlyCentavos, int $daysWithoutPay, int $absentMinutes): int
    {
        $minutes = $daysWithoutPay * self::MINUTES_PER_DAY + $absentMinutes * 1000;

        return Money::ratio($monthlyCentavos, $minutes, self::WORKING_DAYS_PER_MONTH * self::MINUTES_PER_DAY * 1000);
    }

    /**
     * Months before the period, from the configured start (or the month of
     * hiring) and at most LOOKBACK_MONTHS back.
     *
     * @return list<CarbonImmutable>
     */
    private function candidateMonths(Employee $employee, PayrollPeriod $period): array
    {
        $from = $this->settings->attendanceDeductionsFrom();
        $last = $period->monthStart()->subMonthNoOverflow();

        if ($from === null) {
            return [];
        }

        $start = collect([
            $from->startOfMonth(),
            CarbonImmutable::parse($employee->date_hired)->startOfMonth(),
            $period->monthStart()->subMonthsNoOverflow(self::LOOKBACK_MONTHS),
        ])->max();

        $months = [];

        for ($month = $start; $month->lessThanOrEqualTo($last); $month = $month->addMonthNoOverflow()) {
            $months[] = $month;
        }

        return $months;
    }

    /**
     * Approved leave without pay plus tardiness beyond vacation leave, in thousandths of a day.
     */
    private function daysWithoutPay(Employee $employee, CarbonImmutable $month): int
    {
        $unpaidLeave = LeaveRequestDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->whereHas('leaveRequest', fn ($query) => $query->where('status', LeaveRequestStatus::Approved))
            ->get(['days', 'days_with_pay'])
            ->sum(fn (LeaveRequestDay $day): int => LeaveDays::toThousandths($day->days) - LeaveDays::toThousandths($day->days_with_pay));

        $unpaidTardiness = $employee->leaveLedgerEntries()
            ->where('leave_type_id', LeaveType::vacation()->id)
            ->where('entry_type', LeaveLedgerEntryType::Tardiness)
            ->whereDate('period', $month->toDateString())
            ->get(['days_without_pay'])
            ->sum(fn ($entry): int => LeaveDays::toThousandths($entry->days_without_pay));

        return $unpaidLeave + $unpaidTardiness;
    }
}
