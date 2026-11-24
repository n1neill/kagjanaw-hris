<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SignInService;
use App\Services\Auth\TwoFactorChallenge;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * The second sign-in step: the user enters the code emailed to the address
 * on their account.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private TwoFactorChallenge $challenge) {}

    public function create(): View|RedirectResponse
    {
        $user = $this->challenge->pendingUser();

        if ($user === null) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge', [
            'maskedEmail' => $this->maskEmail($user->email),
            'codeLifetime' => $this->challenge->codeLifetime(),
        ]);
    }

    public function store(Request $request, SignInService $signIn): RedirectResponse
    {
        $user = $this->challenge->pendingUser();

        if ($user === null) {
            return $this->restartSignIn(__('Your sign-in session has ended. Please sign in again.'));
        }

        $request->merge(['code' => preg_replace('/\D/', '', (string) $request->input('code'))]);

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if ($this->challenge->hasExpired()) {
            return back()->withErrors(['code' => __('This code has expired. Send a new code and try again.')]);
        }

        if (! $this->challenge->verify($validated['code'])) {
            event(new Failed('web', $user, []));

            if ($this->challenge->attemptsExhausted()) {
                return $this->restartSignIn(__('Too many incorrect codes. Please sign in again.'));
            }

            return back()->withErrors(['code' => __('The code is incorrect.')]);
        }

        $remember = $this->challenge->remember();
        $this->challenge->clear();

        $signIn->signIn($request, $user, $remember);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(): RedirectResponse
    {
        $user = $this->challenge->pendingUser();

        if ($user === null) {
            return $this->restartSignIn(__('Your sign-in session has ended. Please sign in again.'));
        }

        try {
            $this->challenge->sendNewCode($user);
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->withErrors(['code' => __('The code could not be sent right now. Please try again later or contact the System Administrator.')]);
        }

        return back()->with('status', __('A new code has been sent to your email.'));
    }

    private function restartSignIn(string $message): RedirectResponse
    {
        $this->challenge->clear();

        return redirect()->route('login')->withErrors(['username' => $message]);
    }

    /**
     * Show enough of the address for the user to recognise it, e.g. j*******@gmail.com.
     */
    private function maskEmail(string $email): string
    {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::mask($localPart, '*', 1).'@'.$domain;
    }
}
