<?php

namespace Database\Factories;

use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAllowance>
 */
class EmployeeAllowanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'allowance_type_id' => AllowanceType::factory(),
            'monthly_amount' => null,
            'starts_on' => null,
            'ends_on' => null,
        ];
    }
}
