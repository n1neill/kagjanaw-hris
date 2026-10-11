<?php

namespace Database\Factories;

use App\Enums\CivilStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Enums\Sex;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sex = fake()->randomElement(Sex::cases());
        $dateHired = fake()->dateTimeBetween('-25 years', '-1 month');

        return [
            'employee_number' => $dateHired->format('Y').'-'.fake()->unique()->numerify('####'),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName($sex === Sex::Male ? 'male' : 'female'),
            'middle_name' => fake()->optional(0.9)->lastName(),
            'name_extension' => null,
            'sex' => $sex,
            'civil_status' => fake()->randomElement(CivilStatus::cases()),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-21 years')->format('Y-m-d'),
            'birth_place' => 'Cagdianao, Dinagat Islands',
            'mobile_number' => '09'.fake()->numerify('#########'),
            'residential_address' => 'Purok '.fake()->numberBetween(1, 7).', Brgy. '.fake()->randomElement(['Poblacion', 'Valencia', 'San Jose', 'Del Pilar', 'Legaspi', 'Tigbao']).', Cagdianao, Dinagat Islands',
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_number' => '09'.fake()->numerify('#########'),
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'employment_type_id' => EmploymentType::factory(),
            'salary_grade' => fake()->numberBetween(1, 24),
            'salary_step' => fake()->numberBetween(1, 8),
            'date_hired' => $dateHired->format('Y-m-d'),
            'employment_status' => EmploymentStatus::Active,
            'separation_date' => null,
            'gsis_number' => fake()->numerify('###########'),
            'philhealth_number' => fake()->numerify('############'),
            'pagibig_number' => fake()->numerify('############'),
            'tin' => fake()->numerify('#########'),
            'biometric_user_id' => (string) fake()->unique()->numberBetween(1, 99999),
        ];
    }

    /**
     * Link a login account whose username is the employee number. The closure
     * runs after create() overrides are applied, so it sees the final number.
     */
    public function withAccount(Role $role = Role::Employee): static
    {
        return $this->state([
            'user_id' => fn (array $attributes): int => User::factory()->withRole($role)->create([
                'username' => $attributes['employee_number'],
                'name' => $attributes['first_name'].' '.$attributes['last_name'],
            ])->id,
        ]);
    }

    public function separated(EmploymentStatus $status = EmploymentStatus::Resigned): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => $status,
            'separation_date' => now()->subMonth()->toDateString(),
        ]);
    }
}
