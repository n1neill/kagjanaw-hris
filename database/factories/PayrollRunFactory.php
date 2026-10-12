<?php

namespace Database\Factories;

use App\Enums\PayBasis;
use App\Enums\PayrollRunStatus;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    /**
     * Define the model's default state: a draft regular payroll.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_period_id' => PayrollPeriod::factory(),
            'pay_basis' => PayBasis::SalaryGrade,
            'status' => PayrollRunStatus::Draft,
        ];
    }

    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrollRunStatus::Released,
            'released_at' => now(),
        ]);
    }
}
