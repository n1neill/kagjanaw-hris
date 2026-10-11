<?php

namespace Database\Seeders;

use App\Enums\CivilStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Enums\Sex;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo logins (one per role) plus sample employees, for demos and local
 * development. Never run in production.
 */
class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'Kagjanaw@2026';

    private const SAMPLE_EMPLOYEE_COUNT = 30;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(['username' => 'admin'], [
            'name' => 'System Administrator',
            'email' => 'admin@cagdianao.local',
            'password' => self::PASSWORD,
            'is_active' => true,
        ]);
        $admin->syncRoles([Role::SystemAdministrator]);

        $this->seedEmployeeAccount(Role::HrOfficer, [
            'employee_number' => '2015-0101',
            'last_name' => 'Santos',
            'first_name' => 'Maria',
            'middle_name' => 'Lopez',
            'sex' => Sex::Female,
            'department' => 'MHRMO',
            'position' => 'Human Resource Management Officer I',
            'date_hired' => '2015-03-02',
        ]);

        $this->seedEmployeeAccount(Role::DepartmentHead, [
            'employee_number' => '2010-0045',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Garcia',
            'sex' => Sex::Male,
            'department' => 'MHO',
            'position' => 'Municipal Government Department Head I',
            'date_hired' => '2010-07-01',
        ]);

        $this->seedEmployeeAccount(Role::Employee, [
            'employee_number' => '2021-0230',
            'last_name' => 'Reyes',
            'first_name' => 'Ana',
            'middle_name' => 'Bautista',
            'sex' => Sex::Female,
            'department' => 'MHO',
            'position' => 'Nurse I',
            'date_hired' => '2021-01-04',
        ]);

        $this->seedSampleEmployees();
    }

    /**
     * @param  array{employee_number: string, last_name: string, first_name: string, middle_name: string, sex: Sex, department: string, position: string, date_hired: string}  $details
     */
    private function seedEmployeeAccount(Role $role, array $details): void
    {
        $position = Position::where('title', $details['position'])->sole();

        $user = User::updateOrCreate(['username' => $details['employee_number']], [
            'name' => $details['first_name'].' '.mb_substr($details['middle_name'], 0, 1).'. '.$details['last_name'],
            'password' => self::PASSWORD,
            'is_active' => true,
        ]);
        $user->syncRoles([$role]);

        $employee = Employee::firstOrNew(['employee_number' => $details['employee_number']]);

        if (! $employee->exists) {
            $employee->fill(Employee::factory()->raw([
                'employee_number' => $details['employee_number'],
                'last_name' => $details['last_name'],
                'first_name' => $details['first_name'],
                'middle_name' => $details['middle_name'],
                'sex' => $details['sex'],
                'civil_status' => CivilStatus::Married,
                'department_id' => Department::where('code', $details['department'])->sole()->id,
                'position_id' => $position->id,
                'employment_type_id' => EmploymentType::where('code', 'permanent')->sole()->id,
                'salary_grade' => $position->salary_grade,
                'salary_step' => 1,
                'date_hired' => $details['date_hired'],
            ]));
        }

        $employee->user()->associate($user)->save();
    }

    /**
     * Sample employees with Employee-role accounts that must change their password.
     * A third of them work in the demo department head's office (MHO).
     */
    private function seedSampleEmployees(): void
    {
        if (Employee::count() >= self::SAMPLE_EMPLOYEE_COUNT) {
            return;
        }

        $departments = Department::where('is_active', true)->get();
        $healthOffice = $departments->firstWhere('code', 'MHO');
        $positions = Position::where('is_active', true)->where('salary_grade', '<', 24)->get();
        $permanent = EmploymentType::where('code', 'permanent')->sole();
        $otherTypes = EmploymentType::whereIn('code', ['casual', 'coterminous', 'job-order'])->get();

        Employee::factory()
            ->count(self::SAMPLE_EMPLOYEE_COUNT)
            ->withAccount(Role::Employee)
            ->sequence(fn ($sequence) => [
                'department_id' => $sequence->index % 3 === 0 ? $healthOffice->id : $departments->random()->id,
                'position_id' => $positions->random()->id,
                'employment_type_id' => $sequence->index % 5 === 0 ? $otherTypes->random()->id : $permanent->id,
                'employment_status' => $sequence->index % 14 === 13 ? EmploymentStatus::Retired : EmploymentStatus::Active,
                'separation_date' => $sequence->index % 14 === 13 ? now()->subMonths(2)->toDateString() : null,
            ])
            ->create()
            ->each(function (Employee $employee): void {
                $employee->forceFill(['salary_grade' => $employee->position->salary_grade])->save();
                $employee->user->forceFill([
                    'must_change_password' => true,
                    'is_active' => $employee->employment_status->isInService(),
                ])->save();
            });
    }
}
