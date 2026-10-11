<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Renders the dashboard that matches the signed-in user's primary role.
 */
class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $role = $user->primaryRole();

        abort_if($role === null, 403, __('Your account has no role assigned. Please contact the System Administrator.'));

        return view($role->dashboardView(), match ($role) {
            Role::SystemAdministrator => $this->administratorData(),
            Role::HrOfficer => $this->hrData(),
            Role::DepartmentHead => $this->departmentHeadData($user),
            Role::Employee => $this->employeeData($user),
        });
    }

    /**
     * @return array{totalUsers: int, activeUsers: int, inactiveUsers: int, usersPerRole: array<string, int>}
     */
    private function administratorData(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::active()->count();

        $countsByRoleName = RoleModel::query()->withCount('users')->pluck('users_count', 'name');

        $usersPerRole = [];

        foreach (Role::cases() as $role) {
            $usersPerRole[$role->label()] = (int) $countsByRoleName->get($role->value, 0);
        }

        return [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'inactiveUsers' => $totalUsers - $activeUsers,
            'usersPerRole' => $usersPerRole,
        ];
    }

    /**
     * @return array{employeesInService: int, separatedEmployees: int}
     */
    private function hrData(): array
    {
        $countsByStatus = Employee::query()
            ->selectRaw('employment_status, count(*) as total')
            ->groupBy('employment_status')
            ->pluck('total', 'employment_status');

        $employeesInService = (int) $countsByStatus->get(EmploymentStatus::Active->value, 0);

        return [
            'employeesInService' => $employeesInService,
            'separatedEmployees' => (int) $countsByStatus->sum() - $employeesInService,
        ];
    }

    /**
     * @return array{department: ?Department, teamSize: int}
     */
    private function departmentHeadData(User $user): array
    {
        $department = $user->employee?->department;

        return [
            'department' => $department,
            'teamSize' => $department === null
                ? 0
                : Employee::query()->inService()->where('department_id', $department->id)->count(),
        ];
    }

    /**
     * @return array{employee: ?Employee}
     */
    private function employeeData(User $user): array
    {
        return [
            'employee' => $user->employee?->load(['department', 'position']),
        ];
    }
}
