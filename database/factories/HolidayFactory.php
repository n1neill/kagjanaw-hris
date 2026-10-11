<?php

namespace Database\Factories;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeThisYear()->format('Y-m-d'),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(HolidayType::cases()),
        ];
    }
}
