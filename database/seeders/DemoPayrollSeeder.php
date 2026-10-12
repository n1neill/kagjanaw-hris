<?php

namespace Database\Seeders;

use App\Enums\AllowanceCoverage;
use App\Enums\PayBasis;
use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Attendance\DtrCorrectionService;
use App\Services\Payroll\PayrollRunService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * Payroll for the demo, built through the real services: daily rates for
 * Job Order workers, a few loans and an assigned allowance, last month's two
 * payrolls released (payslips visible to employees), and this month's first
 * payroll computed for review. Development only.
 */
class DemoPayrollSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(PayrollRunService $payroll, DtrCorrectionService $corrections): void
    {
        if (PayrollRun::query()->exists()) {
            return;
        }

        // Compute in-process; the demo has no queue worker running yet.
        config(['queue.default' => 'sync']);

        $hr = User::query()->where('username', '2015-0101')->sole();
        $lastMonth = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        $this->seedDailyRates($corrections, $lastMonth, $hr);
        $this->seedDeductions($hr);
        $this->seedRata();

        foreach ([1, 2] as $half) {
            foreach (PayBasis::cases() as $payBasis) {
                $run = $payroll->create($lastMonth->year, $lastMonth->month, $half, $payBasis, $hr);
                $run = $payroll->requestComputation($run, $hr)->fresh();

                try {
                    $payroll->release($payroll->approve($run, $hr), $hr);
                } catch (ValidationException) {
                    // Left computed with its problems listed, as HR would find it.
                }
            }
        }

        $thisMonth = CarbonImmutable::now();
        $payroll->requestComputation($payroll->create($thisMonth->year, $thisMonth->month, 1, PayBasis::SalaryGrade, $hr), $hr);
    }

    /**
     * Daily rates for Job Order / Contract of Service workers, whose DTR for
     * last month is also validated so their payroll can be released.
     */
    private function seedDailyRates(DtrCorrectionService $corrections, CarbonImmutable $lastMonth, User $hr): void
    {
        Employee::query()
            ->whereHas('employmentType', fn ($query) => $query->where('pay_basis', PayBasis::DailyRate))
            ->orderBy('id')
            ->get()
            ->each(function (Employee $employee, int $index) use ($corrections, $lastMonth, $hr): void {
                $employee->forceFill(['daily_rate' => ['550.00', '600.00', '650.00'][$index % 3], 'salary_grade' => null, 'salary_step' => null])->save();

                try {
                    $corrections->validateMonth($employee, $lastMonth, $hr);
                } catch (ValidationException) {
                    // Days still needing correction stay unvalidated.
                }
            });
    }

    private function seedDeductions(User $hr): void
    {
        $employees = Employee::query()
            ->whereHas('employmentType', fn ($query) => $query->where('pay_basis', PayBasis::SalaryGrade)->where('earns_leave_credits', true))
            ->inService()
            ->orderBy('id')
            ->limit(8)
            ->get();

        foreach ($employees as $index => $employee) {
            $deductions = [
                ['name' => 'GSIS Consolidated Loan', 'reference' => 'CL-'.(240100 + $index), 'monthly_amount' => '2750.00', 'total_amount' => '99000.00', 'priority' => 10],
                ['name' => 'Pag-IBIG Multi-Purpose Loan', 'reference' => 'MPL-'.(88000 + $index), 'monthly_amount' => '1150.00', 'total_amount' => '27600.00', 'priority' => 20],
                ['name' => 'Cagdianao Employees Multi-Purpose Cooperative', 'reference' => null, 'monthly_amount' => '500.00', 'total_amount' => null, 'priority' => 50],
            ];

            foreach (array_slice($deductions, 0, $index % 3 + 1) as $deduction) {
                EmployeeDeduction::query()->create([
                    ...$deduction,
                    'employee_id' => $employee->id,
                    'starts_on' => CarbonImmutable::now()->subMonthsNoOverflow(6)->startOfMonth()->toDateString(),
                ])->forceFill(['created_by' => $hr->id])->save();
            }
        }
    }

    /**
     * Representation and Transportation Allowance for department heads, at an
     * illustrative amount HR must replace with the authorized rate.
     */
    private function seedRata(): void
    {
        $rata = AllowanceType::query()->firstOrCreate(['code' => 'RATA'], [
            'name' => 'Representation and Transportation Allowance (RATA)',
            'description' => 'For department heads and officials as authorized by the General Appropriations Act and DBM issuances. Demo amount: replace with the authorized rate.',
            'monthly_amount' => '8500.00',
            'coverage' => AllowanceCoverage::Assigned,
            'is_taxable' => false,
            'reduced_for_unpaid_days' => false,
            'is_active' => true,
        ]);

        $head = Employee::query()->where('employee_number', '2010-0045')->first();

        if ($head !== null) {
            EmployeeAllowance::query()->firstOrCreate(['employee_id' => $head->id, 'allowance_type_id' => $rata->id]);
        }
    }
}
