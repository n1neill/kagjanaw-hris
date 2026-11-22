<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserPermissionsRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;

/**
 * Grants or removes HR administration access on top of an account's role.
 * The change takes effect immediately.
 */
class UserPermissionController extends Controller
{
    public function update(UpdateUserPermissionsRequest $request, User $user, UserService $users): RedirectResponse
    {
        $users->syncGrantedPermissions($user, $request->permissions());

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', __('Additional access for :name updated.', ['name' => $user->name]));
    }
}
