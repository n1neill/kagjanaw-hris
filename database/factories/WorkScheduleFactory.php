<?php

namespace Database\Factories;

use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    /**
     * Define the model's default state: regular municipal office hours.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Regular Office Hours',
            'am_start' => '08:00',
            'am_end' => '12:00',
            'pm_start' => '13:00',
            'pm_end' => '17:00',
            'grace_minutes' => 15,
            'working_days' => [1, 2, 3, 4, 5],
            'is_default' => true,
        ];
    }
}
