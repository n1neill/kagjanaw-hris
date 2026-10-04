<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Account management rules. Administrators may not change their own role or
 * deactivate themselves, so the system can never be left without an admin
 * by accident.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageUsers);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageUsers);
    }

    public function update(User $user, User $account): bool
    {
        return $user->can(Permission::ManageUsers);
    }

    public function changeRole(User $user, User $account): bool
    {
        return $user->can(Permission::ManageUsers) && $user->isNot($account);
    }

    public function changeStatus(User $user, User $account): bool
    {
        return $user->can(Permission::ManageUsers) && $user->isNot($account);
    }
}
