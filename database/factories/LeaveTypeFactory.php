<?php

namespace Database\Factories;

use App\Enums\LeaveCreditSource;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The standard leave types are seeded (Database\Seeders\LeaveTypeSeeder);
 * use this factory for additional or custom types.
 *
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('X???'),
            'name' => ucwords(fake()->unique()->words(2, true)).' Leave',
            'description' => fake()->sentence(),
            'credit_source' => LeaveCreditSource::NotCharged,
            'max_days_per_request' => '10.000',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
