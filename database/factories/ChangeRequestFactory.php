<?php

namespace Database\Factories;

use App\Enums\ChangeAction;
use App\Enums\ChangeRequestStatus;
use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeRequest>
 */
class ChangeRequestFactory extends Factory
{
    /**
     * Define the model's default state: a pending new department.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Office of the '.fake()->unique()->jobTitle();

        return [
            'action' => ChangeAction::CreateDepartment,
            'description' => 'Add department: '.$name,
            'payload' => ['name' => $name, 'code' => strtoupper(fake()->unique()->lexify('???')), 'is_active' => true],
            'files' => null,
            'status' => ChangeRequestStatus::Pending,
            'requested_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChangeRequestStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChangeRequestStatus::Rejected,
            'reviewed_at' => now(),
            'review_remarks' => 'Not needed.',
        ]);
    }
}
