<?php

namespace Database\Factories;

use App\Enums\Finger;
use App\Models\Employee;
use App\Models\FingerprintTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FingerprintTemplate>
 */
class FingerprintTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'finger' => Finger::RightIndex,
            'template' => base64_encode(random_bytes(600)),
            'quality' => fake()->numberBetween(60, 100),
        ];
    }
}
