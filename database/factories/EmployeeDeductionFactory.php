<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeDeduction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDeduction>
 */
class EmployeeDeductionFactory extends Factory
{
    /**
     * Define the model's default state: an open-ended monthly deduction.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'name' => fake()->randomElement(['GSIS Consolidated Loan', 'Pag-IBIG Multi-Purpose Loan', 'Cooperative Share Capital', 'Employees\' Union Dues']),
            'reference' => fake()->optional()->numerify('LN-######'),
            'monthly_amount' => '1000.00',
            'total_amount' => null,
            'starts_on' => '2026-01-01',
            'ends_on' => null,
            'priority' => 100,
            'is_active' => true,
            'remarks' => null,
        ];
    }

    /**
     * A loan with a total to be paid off.
     */
    public function loan(string $total): static
    {
        return $this->state(fn (array $attributes) => [
            'total_amount' => $total,
        ]);
    }
}
