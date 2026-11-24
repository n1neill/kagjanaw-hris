<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Check the username and password without signing in, and return the
     * active account they belong to. Signing in happens afterwards, once any
     * emailed code has also been entered.
     *
     * @throws ValidationException
     */
    public function validateCredentials(): User
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('username', 'password');

        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');

        $hasValidCredentials = $guard->validate($credentials);

        /** @var User|null $user */
        $user = $guard->getLastAttempted();

        if (! $hasValidCredentials || ! $user->is_active) {
            RateLimiter::hit($this->throttleKey());

            event(new Failed('web', $user, $credentials));

            throw ValidationException::withMessages([
                'username' => $hasValidCredentials
                    ? __('Your account has been deactivated. Please contact the System Administrator.')
                    : trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if (config('hashing.rehash_on_login', true)) {
            $guard->getProvider()->rehashPasswordIfRequired($user, $credentials);
        }

        return $user;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}
