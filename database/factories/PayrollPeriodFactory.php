<?php

namespace Database\Factories;

use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    /**
     * Define the model's default state: September 1–15, 2026. Prefer
     * PayrollPeriod::for() in tests; it derives the dates.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => 2026,
            'month' => 9,
            'half' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-15',
        ];
    }
}
