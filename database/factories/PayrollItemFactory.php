<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollItem>
 */
class PayrollItemFactory extends Factory
{
    /**
     * Define the model's default state: half a month of SG 11, Step 1 at 70%.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_run_id' => PayrollRun::factory(),
            'employee_id' => Employee::factory(),
            'department_name' => 'Office of the Municipal Mayor',
            'position_title' => 'Administrative Officer II',
            'salary_grade' => 11,
            'salary_step' => 1,
            'monthly_rate' => '22194.00',
            'basic_pay' => '11097.00',
            'allowances' => '1000.00',
            'attendance_deductions' => '0.00',
            'gross_pay' => '12097.00',
            'earnings' => [
                ['label' => 'Basic salary (SG 11, Step 1)', 'amount' => '11097.00'],
                ['label' => 'Personnel Economic Relief Allowance (PERA)', 'amount' => '1000.00'],
            ],
            'gsis' => '998.73',
            'philhealth' => '277.43',
            'pagibig' => '100.00',
            'withholding_tax' => '0.00',
            'other_deductions' => '0.00',
            'total_deductions' => '1376.16',
            'net_pay' => '10720.84',
            'gsis_employer' => '1331.64',
            'philhealth_employer' => '277.43',
            'pagibig_employer' => '100.00',
            'notes' => null,
        ];
    }
}
