<?php

namespace Database\Factories;

use App\Enums\SyncStatus;
use App\Models\BiometricDevice;
use App\Models\DeviceSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceSyncRun>
 */
class DeviceSyncRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-2 days');

        return [
            'biometric_device_id' => BiometricDevice::factory(),
            'status' => SyncStatus::Succeeded,
            'started_at' => $startedAt,
            'finished_at' => $startedAt,
            'fetched_count' => 40,
            'inserted_count' => 20,
            'duplicate_count' => 20,
            'flagged_count' => 0,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::Failed,
            'fetched_count' => 0,
            'inserted_count' => 0,
            'duplicate_count' => 0,
            'error_message' => 'Device did not respond.',
        ]);
    }
}
