<?php

namespace App\Http\Controllers;

use App\Enums\Role;
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
        $role = $request->user()->primaryRole();

        abort_if($role === null, 403, __('Your account has no role assigned. Please contact the System Administrator.'));

        return view($role->dashboardView(), match ($role) {
            Role::SystemAdministrator => $this->administratorData(),
            default => [],
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
}
