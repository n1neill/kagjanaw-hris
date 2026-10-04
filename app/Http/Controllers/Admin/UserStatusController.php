<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;

/**
 * Activates or deactivates a user account.
 */
class UserStatusController extends Controller
{
    public function update(UpdateUserStatusRequest $request, User $user, UserService $users): RedirectResponse
    {
        $users->setActive($user, $request->boolean('is_active'));

        $message = $user->is_active
            ? __('Account for :name activated.', ['name' => $user->name])
            : __('Account for :name deactivated.', ['name' => $user->name]);

        return back()->with('status', $message);
    }
}
