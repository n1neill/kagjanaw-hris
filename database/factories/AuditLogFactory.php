<?php

namespace Database\Factories;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event' => AuditEvent::Updated,
            'description' => fake()->sentence(3),
            'ip_address' => fake()->localIpv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }
}
