<?php

namespace Database\Factories;

use App\Models\BiometricDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiometricDevice>
 */
class BiometricDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Main Lobby', 'Health Office', 'Engineering Office']).' Time Clock',
            'driver' => 'mock',
            'ip_address' => fake()->localIpv4(),
            'port' => 4370,
            'location' => 'Municipal Hall',
            'is_active' => true,
        ];
    }

    /**
     * A device that does not answer (the mock driver treats 0.0.0.0 as unreachable).
     */
    public function unreachable(): static
    {
        return $this->state(fn (array $attributes) => [
            'ip_address' => '0.0.0.0',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * A DigitalPersona kiosk PC that pushes punches, signing in with the given token.
     */
    public function kiosk(string $token = 'test-kiosk-token', string $ipAddress = '127.0.0.1'): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Lobby Fingerprint Kiosk',
            'driver' => 'digitalpersona',
            'ip_address' => $ipAddress,
            'port' => 443,
            'api_token_hash' => hash('sha256', $token),
        ]);
    }
}
