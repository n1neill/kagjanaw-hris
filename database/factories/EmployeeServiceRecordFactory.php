<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeServiceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeServiceRecord>
 */
class EmployeeServiceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = fake()->dateTimeBetween('-20 years', '-2 years');

        return [
            'employee_id' => Employee::factory(),
            'service_from' => $from->format('Y-m-d'),
            'service_to' => fake()->dateTimeBetween($from, '-1 year')->format('Y-m-d'),
            'designation' => fake()->randomElement(['Administrative Aide I', 'Administrative Assistant II', 'Municipal Agriculturist', 'Nurse I', 'Engineer II']),
            'status' => fake()->randomElement(['Permanent', 'Casual', 'Temporary', 'Coterminous']),
            'annual_salary' => fake()->numberBetween(150_000, 600_000).'.00',
            'station' => fake()->randomElement(['Office of the Mayor', 'Municipal Treasurer\'s Office', 'Municipal Health Office', 'Municipal Engineering Office']),
            'branch' => 'Local',
            'leave_without_pay' => null,
            'separation_date' => null,
            'separation_cause' => null,
            'remarks' => null,
            'created_by' => null,
        ];
    }

    /**
     * The current, open-ended appointment.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_to' => null,
        ]);
    }
}
