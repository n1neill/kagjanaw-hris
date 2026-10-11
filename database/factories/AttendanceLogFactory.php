<?php

namespace Database\Factories;

use App\Enums\AttendanceLogStatus;
use App\Enums\AttendanceSource;
use App\Enums\VerifyType;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceLog>
 */
class AttendanceLogFactory extends Factory
{
    /**
     * Define the model's default state: a valid punch by an employee.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'biometric_device_id' => null,
            'employee_id' => Employee::factory(),
            'biometric_user_id' => fn (array $attributes): string => (string) Employee::find($attributes['employee_id'])->biometric_user_id,
            'logged_at' => fake()->dateTimeBetween('-1 week', '-1 day'),
            'verify_type' => VerifyType::Fingerprint,
            'source' => AttendanceSource::Device,
            'status' => AttendanceLogStatus::Valid,
        ];
    }

    /**
     * A punch from a biometric ID that no employee has.
     */
    public function unknownUser(string $biometricUserId = '99999'): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => null,
            'biometric_user_id' => $biometricUserId,
            'status' => AttendanceLogStatus::UnknownUser,
        ]);
    }
}
