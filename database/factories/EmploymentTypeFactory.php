<?php

namespace Database\Factories;

use App\Enums\PayBasis;
use App\Models\EmploymentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentType>
 */
class EmploymentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => str($name)->slug()->limit(30, '')->toString(),
            'name' => ucwords($name),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Job Order / Contract of Service: paid by daily rate, no leave credits.
     */
    public function dailyRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_basis' => PayBasis::DailyRate,
            'earns_leave_credits' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
