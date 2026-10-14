<?php

namespace Database\Factories;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'body' => fake()->paragraphs(2, true),
            'audience' => AnnouncementAudience::Everyone,
            'is_pinned' => false,
            'published_at' => now()->subHour(),
            'expires_on' => null,
            'created_by' => User::factory()->hrOfficer(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['published_at' => null]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_on' => now()->subDay()->toDateString()]);
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => ['is_pinned' => true]);
    }

    public function forDepartments(Department ...$departments): static
    {
        return $this->state(fn (array $attributes) => ['audience' => AnnouncementAudience::Departments])
            ->afterCreating(fn (Announcement $announcement) => $announcement->departments()->attach(collect($departments)->pluck('id')));
    }
}
