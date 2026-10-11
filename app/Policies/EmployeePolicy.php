<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Least-privilege access to employee records (RA 10173):
 *  - HR officers manage every record and its 201 file.
 *  - Department heads see the basic profile of employees in their own department.
 *  - Employees see their own full record and documents.
 *
 * Denied record access responds as "not found" so that guessing IDs in the
 * URL does not reveal which records exist.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageEmployees) || $user->can(Permission::ViewDepartmentEmployees);
    }

    public function view(User $user, Employee $employee): Response
    {
        if ($user->can(Permission::ManageEmployees) || $this->isOwnRecord($user, $employee)) {
            return Response::allow();
        }

        if ($user->can(Permission::ViewDepartmentEmployees) && $this->isInUsersDepartment($user, $employee)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /**
     * Government IDs, salary, birth details, address and the 201 file.
     */
    public function viewSensitive(User $user, Employee $employee): bool
    {
        return $user->can(Permission::ManageEmployees) || $this->isOwnRecord($user, $employee);
    }

    public function viewDocuments(User $user, Employee $employee): Response
    {
        return $this->viewSensitive($user, $employee)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageEmployees);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can(Permission::ManageEmployees);
    }

    public function manageDocuments(User $user, Employee $employee): bool
    {
        return $user->can(Permission::ManageEmployees);
    }

    /**
     * The Daily Time Record: HR, the employee's department head, and the employee.
     */
    public function viewAttendance(User $user, Employee $employee): Response
    {
        $allowed = $user->can(Permission::ManageAttendance)
            || ($user->can(Permission::ViewOwnAttendance) && $employee->user_id !== null && $employee->user_id === $user->id)
            || ($user->can(Permission::ViewDepartmentAttendance) && $this->isInUsersDepartment($user, $employee));

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Correcting, validating and recomputing a Daily Time Record.
     */
    public function manageAttendance(User $user, Employee $employee): bool
    {
        return $user->can(Permission::ManageAttendance);
    }

    /**
     * The leave card (ledger) and balances: HR and the employee.
     */
    public function viewLeaveCard(User $user, Employee $employee): Response
    {
        $allowed = $user->can(Permission::ManageLeaveCredits)
            || $user->can(Permission::ApproveLeave)
            || ($user->can(Permission::FileLeave) && $employee->user_id !== null && $employee->user_id === $user->id);

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }

    private function isOwnRecord(User $user, Employee $employee): bool
    {
        return $employee->user_id !== null
            && $employee->user_id === $user->id
            && $user->can(Permission::ViewOwnRecords);
    }

    private function isInUsersDepartment(User $user, Employee $employee): bool
    {
        $departmentId = $user->employee?->department_id;

        return $departmentId !== null && $departmentId === $employee->department_id;
    }
}
