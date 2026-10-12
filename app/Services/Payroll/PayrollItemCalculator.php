<?php

namespace App\Services\Payroll;

use App\Enums\ContributionType;
use App\Enums\PayBasis;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendanceReportService;
use App\Services\Leave\LeaveDays;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Computes one employee's pay for a semi-monthly payroll run.
 *
 * Salary-grade employees: half the monthly salary (prorated by working days
 * in service for new hires and separations) plus allowances, less absences
 * and tardiness without pay from earlier months; GSIS, PhilHealth and
 * Pag-IBIG are half the monthly contribution; tax is from the BIR monthly
 * table; loans and other deductions follow, as long as net pay stays at or
 * above half the monthly minimum.
 *
 * Daily-rate (Job Order / COS) workers: daily rate × time worked in the
 * period from the validated DTR, less the tax rate HR sets and any other
 * deductions. No GSIS, PhilHealth or Pag-IBIG.
 *
 * Every amount is in centavos.
 */
class PayrollItemCalculator
{
    public function __construct(
        private SalarySchedule $salaries,
        private ContributionCalculator $contributions,
        private WithholdingTaxCalculator $tax,
        private AllowanceResolver $allowances,
        private AttendanceDeductionCalculator $attendance,
        private PayrollSettings $settings,
    ) {}

    /**
     * @return array{
     *     item: ?array<string, mixed>,
     *     deductions: list<array{employee_deduction_id: int, label: string, amount: int}>,
     *     attendance: list<array{month: CarbonImmutable, days_without_pay: int, absent_minutes: int, salary_amount: int, allowance_amount: int}>,
     *     warnings: list<array{message: string, blocking: bool}>,
     *     waiting: list<CarbonImmutable>,
     * }
     */
    public function compute(Employee $employee, PayrollRun $run): array
    {
        return $run->pay_basis === PayBasis::DailyRate
            ? $this->dailyRate($employee, $run)
            : $this->salaryGrade($employee, $run);
    }

    /**
     * @return array{item: ?array<string, mixed>, deductions: list<array<string, mixed>>, attendance: list<array<string, mixed>>, warnings: list<array{message: string, blocking: bool}>, waiting: list<CarbonImmutable>}
     */
    private function salaryGrade(Employee $employee, PayrollRun $run): array
    {
        $period = $run->period;
        $half = $period->half;
        $date = $period->start_date;

        $monthly = $employee->salary_grade !== null && $employee->salary_step !== null
            ? $this->salaries->monthlySalary($employee->salary_grade, $employee->salary_step, $date)
            : null;

        if ($monthly === null) {
            return $this->skipped(__(':name has no salary grade and step in the Salary Schedule, so was not paid.', ['name' => $employee->sortable_name]));
        }

        [$serviceDays, $periodDays] = $this->workingDays($employee, $period);
        $prorate = fn (int $monthlyAmount): int => Money::ratio(Money::splitMonthly($monthlyAmount)[$half], $serviceDays, max(1, $periodDays));
        $notes = [];
        $warnings = [];

        $basic = $prorate($monthly);
        $earnings = [['label' => __('Basic salary (SG :grade, Step :step)', ['grade' => $employee->salary_grade, 'step' => $employee->salary_step]), 'amount' => $basic]];

        if ($serviceDays < $periodDays) {
            $notes[] = __('Paid for :days of :total working days in the period.', ['days' => $serviceDays, 'total' => $periodDays]);
        }

        $allowanceTotal = 0;
        $taxableAllowances = 0;

        foreach ($this->allowances->for($employee, $period->start_date, $period->end_date) as $allowance) {
            $amount = $prorate($allowance['monthly']);
            $allowanceTotal += $amount;
            $taxableAllowances += $allowance['type']->is_taxable ? $amount : 0;
            $earnings[] = ['label' => $allowance['type']->name, 'amount' => $amount];
        }

        $contributions = [];
        $employer = [];

        foreach ($this->contributions->monthly($monthly, $date) as $type => $shares) {
            $contributions[$type] = $prorate($shares['employee']);
            $employer[$type] = $prorate($shares['employer']);
        }

        // Absences are deducted only as far as pay less contributions allows;
        // the rest is picked up by the next payroll as a difference.
        $attendance = $this->attendance->for($employee, $monthly, $period, $run);
        $attendanceLines = [];
        $attendanceSalary = 0;
        $attendanceTaxableAllowance = 0;
        $available = max(0, $basic + $allowanceTotal - array_sum($contributions));

        foreach ($attendance['months'] as $month) {
            $amount = $month['salary_amount'] + $month['allowance_amount'];

            if ($amount > $available) {
                $notes[] = __(':amount of absences without pay for :month will be deducted in the next payroll.', ['amount' => Money::format($amount - $available), 'month' => $month['month']->format('F Y')]);
                $salaryShare = Money::ratio($month['salary_amount'], $available, $amount);
                $month = [
                    ...$month,
                    'salary_amount' => $salaryShare,
                    'allowance_amount' => $available - $salaryShare,
                    'taxable_allowance_amount' => Money::ratio($month['taxable_allowance_amount'], $available, $amount),
                ];
                $amount = $available;

                if ($amount === 0) {
                    continue;
                }
            }

            $available -= $amount;
            $attendanceSalary += $month['salary_amount'];
            $attendanceTaxableAllowance += $month['taxable_allowance_amount'];
            $attendanceLines[] = $month;
            $earnings[] = ['label' => $this->attendanceLabel($month), 'amount' => -$amount];
        }

        $attendanceTotal = array_sum(array_map(fn (array $month): int => $month['salary_amount'] + $month['allowance_amount'], $attendanceLines));
        $gross = $basic + $allowanceTotal - $attendanceTotal;

        $taxable = $basic - $attendanceSalary + $taxableAllowances - $attendanceTaxableAllowance - array_sum($contributions);
        $withholdingTax = $this->tax->semiMonthlyTax($taxable, $date);

        $net = $gross - array_sum($contributions) - $withholdingTax;
        $minimumNet = Money::splitMonthly($this->settings->minimumNetPay())[$half];
        [$deductionLines, $deductionNotes] = $this->otherDeductions($employee, $run, $net, $minimumNet);
        $otherDeductions = array_sum(array_column($deductionLines, 'amount'));
        $net -= $otherDeductions;

        if ($net < 0) {
            $warnings[] = ['message' => __(':name\'s deductions exceed their pay (net :amount).', ['name' => $employee->sortable_name, 'amount' => Money::format($net)]), 'blocking' => true];
        }

        $totalDeductions = array_sum($contributions) + $withholdingTax + $otherDeductions;

        return [
            'item' => [
                ...$this->snapshot($employee),
                'salary_grade' => $employee->salary_grade,
                'salary_step' => $employee->salary_step,
                'monthly_rate' => Money::toDecimal($monthly),
                'basic_pay' => Money::toDecimal($basic),
                'allowances' => Money::toDecimal($allowanceTotal),
                'attendance_deductions' => Money::toDecimal($attendanceTotal),
                'gross_pay' => Money::toDecimal($gross),
                'earnings' => $this->decimalLines($earnings),
                'gsis' => Money::toDecimal($contributions[ContributionType::Gsis->value]),
                'philhealth' => Money::toDecimal($contributions[ContributionType::PhilHealth->value]),
                'pagibig' => Money::toDecimal($contributions[ContributionType::PagIbig->value]),
                'withholding_tax' => Money::toDecimal($withholdingTax),
                'other_deductions' => Money::toDecimal($otherDeductions),
                'total_deductions' => Money::toDecimal($totalDeductions),
                'net_pay' => Money::toDecimal($net),
                'gsis_employer' => Money::toDecimal($employer[ContributionType::Gsis->value]),
                'philhealth_employer' => Money::toDecimal($employer[ContributionType::PhilHealth->value]),
                'pagibig_employer' => Money::toDecimal($employer[ContributionType::PagIbig->value]),
                'notes' => [...$notes, ...$deductionNotes] ?: null,
            ],
            'deductions' => $deductionLines,
            'attendance' => $attendanceLines,
            'warnings' => $warnings,
            'waiting' => $attendance['waiting'],
        ];
    }

    /**
     * @return array{item: ?array<string, mixed>, deductions: list<array<string, mixed>>, attendance: list<array<string, mixed>>, warnings: list<array{message: string, blocking: bool}>, waiting: list<CarbonImmutable>}
     */
    private function dailyRate(Employee $employee, PayrollRun $run): array
    {
        $period = $run->period;

        if ($employee->daily_rate === null || Money::toCentavos($employee->daily_rate) <= 0) {
            return $this->skipped(__(':name has no daily rate, so was not paid.', ['name' => $employee->sortable_name]));
        }

        $dailyRate = Money::toCentavos($employee->daily_rate);
        $warnings = [];

        $records = $employee->dailyTimeRecords()
            ->whereBetween('work_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
            ->get();
        $daysInService = collect(CarbonPeriod::create($period->start_date, $period->end_date))
            ->filter(fn ($date): bool => $employee->wasEmployedOn(CarbonImmutable::parse($date)))
            ->count();

        if ($records->count() < $daysInService || $records->contains(fn ($record): bool => $record->validated_at === null)) {
            $warnings[] = ['message' => __(':name\'s DTR for the period is not fully validated.', ['name' => $employee->sortable_name]), 'blocking' => true];
        }

        $workedMinutes = (int) $records->sum('worked_minutes');
        $pay = Money::ratio($dailyRate, $workedMinutes, AttendanceDeductionCalculator::MINUTES_PER_DAY);
        $withholdingTax = Money::percentOf($pay, $this->settings->dailyRateTaxRate());
        $net = $pay - $withholdingTax;

        [$deductionLines, $deductionNotes] = $this->otherDeductions($employee, $run, $net, 0);
        $otherDeductions = array_sum(array_column($deductionLines, 'amount'));
        $net -= $otherDeductions;

        return [
            'item' => [
                ...$this->snapshot($employee),
                'daily_rate' => Money::toDecimal($dailyRate),
                'worked_minutes' => $workedMinutes,
                'basic_pay' => Money::toDecimal($pay),
                'allowances' => '0.00',
                'attendance_deductions' => '0.00',
                'gross_pay' => Money::toDecimal($pay),
                'earnings' => $this->decimalLines([[
                    'label' => __('Time worked: :days days (:time) × :rate', [
                        'days' => LeaveDays::format(Money::ratio($workedMinutes, 1000, AttendanceDeductionCalculator::MINUTES_PER_DAY)),
                        'time' => AttendanceReportService::formatMinutes($workedMinutes),
                        'rate' => Money::format($dailyRate),
                    ]),
                    'amount' => $pay,
                ]]),
                'withholding_tax' => Money::toDecimal($withholdingTax),
                'other_deductions' => Money::toDecimal($otherDeductions),
                'total_deductions' => Money::toDecimal($withholdingTax + $otherDeductions),
                'net_pay' => Money::toDecimal($net),
                'notes' => $deductionNotes ?: null,
            ],
            'deductions' => $deductionLines,
            'attendance' => [],
            'warnings' => $warnings,
            'waiting' => [],
        ];
    }

    /**
     * Loans and other recurring deductions in priority order, each limited to
     * what is still owed and to what keeps net pay at the minimum.
     *
     * @return array{0: list<array{employee_deduction_id: int, label: string, amount: int}>, 1: list<string>}
     */
    private function otherDeductions(Employee $employee, PayrollRun $run, int $net, int $minimumNet): array
    {
        $period = $run->period;
        $lines = [];
        $notes = [];

        $deductions = $employee->payrollDeductions()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (EmployeeDeduction $deduction): bool => $deduction->overlaps($period->start_date, $period->end_date));

        foreach ($deductions as $deduction) {
            $due = Money::splitMonthly(Money::toCentavos($deduction->monthly_amount))[$period->half];

            if ($deduction->total_amount !== null) {
                $due = min($due, Money::toCentavos($deduction->total_amount) - $deduction->deductedOutside($run));
            }

            if ($due <= 0) {
                continue;
            }

            $amount = min($due, max(0, $net - $minimumNet));
            $label = $deduction->name.($deduction->reference ? ' ('.$deduction->reference.')' : '');

            if ($amount < $due) {
                $notes[] = __(':amount of :deduction was not deducted to keep net pay at the minimum.', ['amount' => Money::format($due - $amount), 'deduction' => $label]);
            }

            if ($amount > 0) {
                $lines[] = ['employee_deduction_id' => $deduction->id, 'label' => $label, 'amount' => $amount];
                $net -= $amount;
            }
        }

        return [$lines, $notes];
    }

    /**
     * Scheduled working days of the period in service, and in the whole period.
     * Holidays on working days are paid, so they count.
     *
     * @return array{0: int, 1: int}
     */
    private function workingDays(Employee $employee, PayrollPeriod $period): array
    {
        $schedule = WorkSchedule::current();
        $periodDays = 0;
        $serviceDays = 0;

        foreach (CarbonPeriod::create($period->start_date, $period->end_date) as $date) {
            $date = CarbonImmutable::parse($date);

            if (! $schedule->isWorkingDay($date->dayOfWeekIso)) {
                continue;
            }

            $periodDays++;
            $serviceDays += $employee->wasEmployedOn($date) ? 1 : 0;
        }

        return [$serviceDays, $periodDays];
    }

    /**
     * @return array{department_name: string, position_title: string}
     */
    private function snapshot(Employee $employee): array
    {
        return [
            'department_name' => $employee->department->name,
            'position_title' => $employee->position->title,
        ];
    }

    /**
     * @param  array{month: CarbonImmutable, days_without_pay: int, absent_minutes: int, salary_amount: int, allowance_amount: int}  $month
     */
    private function attendanceLabel(array $month): string
    {
        $parts = [];

        if ($month['days_without_pay'] !== 0) {
            $parts[] = __(':days days', ['days' => LeaveDays::format($month['days_without_pay'])]);
        }

        if ($month['absent_minutes'] !== 0) {
            $parts[] = __(':time absent', ['time' => AttendanceReportService::formatMinutes($month['absent_minutes'])]);
        }

        $label = __('Absences and tardiness without pay, :month', ['month' => $month['month']->format('F Y')]);

        return $parts === [] ? $label.' '.__('(correction)') : $label.' ('.implode(', ', $parts).')';
    }

    /**
     * @param  list<array{label: string, amount: int}>  $lines
     * @return list<array{label: string, amount: string}>
     */
    private function decimalLines(array $lines): array
    {
        return array_map(fn (array $line): array => ['label' => $line['label'], 'amount' => Money::toDecimal($line['amount'])], $lines);
    }

    /**
     * @return array{item: null, deductions: list<never>, attendance: list<never>, warnings: list<array{message: string, blocking: bool}>, waiting: list<CarbonImmutable>}
     */
    private function skipped(string $message): array
    {
        return ['item' => null, 'deductions' => [], 'attendance' => [], 'warnings' => [['message' => $message, 'blocking' => true]], 'waiting' => []];
    }
}
