<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates and maintains user accounts and their role assignment.
 * Each user holds exactly one role. A password chosen by someone other than
 * the account owner must be changed by the owner at the next sign-in.
 */
class UserService
{
    /**
     * @param  array{name: string, username: string, email?: string|null, password: string, role: string}  $data
     */
    public function create(array $data, bool $mustChangePassword = true): User
    {
        return DB::transaction(function () use ($data, $mustChangePassword): User {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $user->forceFill(['must_change_password' => $mustChangePassword])->save();

            $user->syncRoles([Role::from($data['role'])]);

            return $user;
        });
    }

    /**
     * Update account details. Only the keys present are changed; a new password
     * set here must be changed by the user at the next sign-in.
     *
     * @param  array{name?: string, username?: string, email?: string|null, password?: string|null, role?: string|null}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->fill(Arr::only($data, ['name', 'username', 'email']));

            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $user->must_change_password = true;
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
