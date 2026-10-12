<?php

namespace Database\Factories;

use App\Enums\AllowanceCoverage;
use App\Models\AllowanceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllowanceType>
 */
class AllowanceTypeFactory extends Factory
{
    /**
     * Define the model's default state: an allowance HR assigns to employees.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' allowance';

        return [
            'code' => mb_strtoupper(fake()->unique()->lexify('???')),
            'name' => ucwords($name),
            'description' => null,
            'monthly_amount' => '1000.00',
            'coverage' => AllowanceCoverage::Assigned,
            'is_taxable' => false,
            'reduced_for_unpaid_days' => false,
            'is_active' => true,
        ];
    }

    public function taxable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_taxable' => true,
        ]);
    }
}
