<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs in a user whose password, and emailed code where one is required,
 * have been checked.
 */
class SignInService
{
    public function signIn(Request $request, User $user, bool $remember): void
    {
        Auth::guard('web')->login($user, $remember);

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
    }
}
