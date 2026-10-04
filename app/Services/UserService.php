<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates and maintains user accounts and their role assignment.
 * Each user holds exactly one role.
 */
class UserService
{
    /**
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $user->syncRoles([Role::from($data['role'])]);

            return $user;
        });
    }

    /**
     * Update account details. The role and password are only changed when present.
     *
     * @param  array{name: string, email: string, password?: string|null, role?: string|null}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();

            if (! empty($data['role'])) {
                $user->syncRoles([Role::from($data['role'])]);
            }

            return $user;
        });
    }

    public function setActive(User $user, bool $isActive): User
    {
        $user->forceFill(['is_active' => $isActive])->save();

        return $user;
    }
}
