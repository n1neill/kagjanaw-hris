<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * The emailed sign-in code a user enters after their password. The pending
 * sign-in is kept in the session between the two steps, and only a hash of
 * the code is stored.
 */
class TwoFactorChallenge
{
    private const SESSION_KEY = 'two_factor_challenge';

    /**
     * Begin a pending sign-in for the user and email them a code.
     *
     * @throws TransportExceptionInterface when the email cannot be sent
     */
    public function start(User $user, bool $remember): void
    {
        session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'remember' => $remember,
        ]);

        $this->sendNewCode($user);
    }

    /**
     * Replace the current code with a new one and email it to the user.
     *
     * @throws TransportExceptionInterface when the email cannot be sent
     */
    public function sendNewCode(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        session()->put(self::SESSION_KEY.'.code', Hash::make($code));
        session()->put(self::SESSION_KEY.'.expires_at', now()->addMinutes($this->codeLifetime())->getTimestamp());
        session()->put(self::SESSION_KEY.'.attempts', 0);

        $user->notify(new TwoFactorCodeNotification($code, $this->codeLifetime()));
    }

    /**
     * The user waiting to enter a code, if they may still sign in with one.
     */
    public function pendingUser(): ?User
    {
        $userId = session()->get(self::SESSION_KEY.'.user_id');

        if ($userId === null) {
            return null;
        }

        $user = User::query()->find($userId);

        if ($user === null || ! $user->is_active || ! $user->requiresTwoFactor()) {
            return null;
        }

        return $user;
    }

    /**
     * Whether the user ticked "Remember me" on the sign-in form.
     */
    public function remember(): bool
    {
        return (bool) session()->get(self::SESSION_KEY.'.remember', false);
    }

    public function hasExpired(): bool
    {
        return now()->getTimestamp() > (int) session()->get(self::SESSION_KEY.'.expires_at', 0);
    }

    /**
     * Check an entered code. Every check counts towards the attempt limit.
     */
    public function verify(string $code): bool
    {
        session()->put(self::SESSION_KEY.'.attempts', $this->attempts() + 1);

        $hashedCode = session()->get(self::SESSION_KEY.'.code');

        return ! $this->hasExpired()
            && is_string($hashedCode)
            && Hash::check($code, $hashedCode);
    }

    public function attemptsExhausted(): bool
    {
        return $this->attempts() >= (int) config('auth.two_factor.max_attempts');
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * How many minutes a code stays valid.
     */
    public function codeLifetime(): int
    {
        return (int) config('auth.two_factor.code_lifetime');
    }

    private function attempts(): int
    {
        return (int) session()->get(self::SESSION_KEY.'.attempts', 0);
    }
}
