<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\SignInService;
use App\Services\Auth\TwoFactorChallenge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request. Accounts that need an
     * emailed code are sent to the code step instead of being signed in.
     */
    public function store(LoginRequest $request, TwoFactorChallenge $challenge, SignInService $signIn): RedirectResponse
    {
        $user = $request->validateCredentials();
        $remember = $request->boolean('remember');

        if ($user->requiresTwoFactor()) {
            try {
                $challenge->start($user, $remember);
            } catch (TransportExceptionInterface $exception) {
                report($exception);

                $challenge->clear();

                return back()->withInput($request->only('username', 'remember'))
                    ->withErrors(['username' => __('Your sign-in code could not be emailed right now. Please try again later or contact the System Administrator.')]);
            }

            return redirect()->route('two-factor.challenge');
        }

        $signIn->signIn($request, $user, $remember);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
