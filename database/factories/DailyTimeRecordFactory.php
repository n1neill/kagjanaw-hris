<?php

namespace Database\Factories;

use App\Enums\DtrStatus;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyTimeRecord>
 */
class DailyTimeRecordFactory extends Factory
{
    /**
     * Define the model's default state: a complete, on-time working day.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'work_date' => fake()->dateTimeBetween('-3 weeks', '-1 day')->format('Y-m-d'),
            'am_in' => '07:55',
            'am_out' => '12:01',
            'pm_in' => '12:55',
            'pm_out' => '17:02',
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'absent_minutes' => 0,
            'worked_minutes' => 480,
            'status' => DtrStatus::Present,
            'is_adjusted' => false,
        ];
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'am_in' => null,
            'am_out' => null,
            'pm_in' => null,
            'pm_out' => null,
            'absent_minutes' => 480,
            'worked_minutes' => 0,
            'status' => DtrStatus::Absent,
        ]);
    }

    public function late(int $minutes = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'am_in' => sprintf('08:%02d', $minutes),
            'late_minutes' => $minutes,
            'worked_minutes' => 480 - $minutes,
        ]);
    }

    public function missingPunch(): static
    {
        return $this->state(fn (array $attributes) => [
            'pm_out' => null,
            'absent_minutes' => 240,
            'worked_minutes' => 240,
            'status' => DtrStatus::Incomplete,
        ]);
    }
}
