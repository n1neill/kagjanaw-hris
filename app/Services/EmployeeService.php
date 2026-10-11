<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Services\Attendance\AttendanceSyncService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Maintains employee records and the login account that belongs to each one.
 *
 * Every employee gets an Employee-role account whose username is the employee
 * number. The account's name and username follow the employee record, and the
 * account is deactivated when the employee separates from service. When an
 * employee is given a biometric user ID, earlier punches from that unknown
 * ID are attached to them.
 */
class EmployeeService
{
    public function __construct(
        private UserService $users,
        private AttendanceSyncService $attendance,
    ) {}

    /**
     * Create the employee and their login account.
     *
     * @param  array<string, mixed>  $data  Validated employee attributes plus an optional "email" for the account.
     * @return array{employee: Employee, temporaryPassword: string}
     */
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $employee = new Employee(Arr::except($data, ['email']));

            $temporaryPassword = $this->generateTemporaryPassword();

            $user = $this->users->create([
                'name' => $employee->full_name,
                'username' => $employee->employee_number,
                'email' => $data['email'] ?? null,
                'password' => $temporaryPassword,
                'role' => Role::Employee->value,
            ]);

            $employee->user()->associate($user);
            $employee->save();

            $this->syncAccountStatus($employee);
            $this->attendance->linkUnknownLogs($employee);

            return ['employee' => $employee, 'temporaryPassword' => $temporaryPassword];
        });
    }

    /**
     * @param  array<string, mixed>  $data  Validated employee attributes plus an optional "email" for the account.
     */
    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data): Employee {
            $statusChanged = isset($data['employment_status'])
                && EmploymentStatus::from($data['employment_status']) !== $employee->employment_status;

            $employee->update(Arr::except($data, ['email']));

            if ($employee->wasChanged('biometric_user_id')) {
                $this->attendance->linkUnknownLogs($employee);
            }

            if ($employee->user !== null) {
                $employee->user->update([
                    'name' => $employee->full_name,
                    'username' => $employee->employee_number,
                    'email' => $data['email'] ?? null,
                ]);

                if ($statusChanged) {
                    $this->syncAccountStatus($employee);
                }
            }

            return $employee;
        });
    }

    /**
     * Separated employees cannot sign in; returning to service restores access.
     */
    private function syncAccountStatus(Employee $employee): void
    {
        $employee->user?->forceFill([
            'is_active' => $employee->employment_status->isInService(),
        ])->save();
    }

    /**
     * A 12-character password that satisfies the password policy, without
     * look-alike characters, so HR can read it out or write it down.
     */
    private function generateTemporaryPassword(): string
    {
        $pools = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnpqrstuvwxyz',
            '23456789',
            '!@#$%*?',
        ];

        $characters = array_map(fn (string $pool): string => $pool[random_int(0, strlen($pool) - 1)], $pools);

        $all = implode('', $pools);

        while (count($characters) < 12) {
            $characters[] = $all[random_int(0, strlen($all) - 1)];
        }

        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swapIndex = random_int(0, $index);
            [$characters[$index], $characters[$swapIndex]] = [$characters[$swapIndex], $characters[$index]];
        }

        return implode('', $characters);
    }
}
