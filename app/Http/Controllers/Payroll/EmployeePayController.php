<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\PayBasis;
use App\Http\Controllers\Controller;
use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Services\Payroll\AllowanceResolver;
use App\Services\Payroll\Money;
use App\Services\Payroll\SalarySchedule;
use Illuminate\View\View;

/**
 * An employee's pay setup: rate, allowances, loans and other deductions,
 * and recent payslips.
 */
class EmployeePayController extends Controller
{
    public function show(Employee $employee, SalarySchedule $schedule, AllowanceResolver $allowances): View
    {
        $employee->load(['employmentType', 'department', 'position', 'allowances.allowanceType']);
        $payBasis = $employee->payBasis();
        $today = now()->toImmutable();

        $monthlySalary = $payBasis === PayBasis::SalaryGrade && $employee->salary_grade !== null && $employee->salary_step !== null
            ? $schedule->monthlySalary($employee->salary_grade, $employee->salary_step, $today)
            : null;

        $deductions = $employee->payrollDeductions()
            ->withSum('payrollLines', 'amount')
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return view('payroll.employees.show', [
            'employee' => $employee,
            'payBasis' => $payBasis,
            'monthlySalary' => $monthlySalary,
            'currentAllowances' => $allowances->for($employee, $today->startOfMonth(), $today->endOfMonth()),
            'availableAllowances' => AllowanceType::query()->active()
                ->whereNotIn('id', $employee->allowances->pluck('allowance_type_id'))
                ->orderBy('name')
                ->pluck('name', 'id'),
            'deductions' => $deductions,
            'deducted' => $deductions->mapWithKeys(fn (EmployeeDeduction $deduction): array => [
                $deduction->id => Money::toCentavos((string) $deduction->payroll_lines_sum_amount),
            ]),
            'payslips' => $employee->payrollItems()->with('run.period')->latest('id')->limit(12)->get(),
        ]);
    }
}
