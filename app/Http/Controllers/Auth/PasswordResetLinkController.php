<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            $this->forgetUnsentToken($request->only('email'));

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('The password reset email could not be sent right now. Please try again later or ask the System Administrator to reset your password.')]);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }

    /**
     * Remove the token of a reset email that never went out, so the user can
     * ask again without waiting for the throttle to pass.
     *
     * @param  array{email: string}  $credentials
     */
    private function forgetUnsentToken(array $credentials): void
    {
        $user = Password::getUser($credentials);

        if ($user !== null) {
            Password::deleteToken($user);
        }
    }
}
