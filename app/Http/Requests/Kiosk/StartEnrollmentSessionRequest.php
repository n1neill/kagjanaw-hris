<?php

namespace App\Http\Requests\Kiosk;

use App\Enums\AuditEvent;
use App\Models\User;
use App\Services\Attendance\Kiosk\KioskEnrollmentSessions;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * An HR officer signing in at a kiosk with their HRIS username and password.
 * Failed and refused attempts are audited; five failures lock the username
 * out at that kiosk for a minute, as on the sign-in page.
 */
class StartEnrollmentSessionRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    /**
     * The kiosk itself was authenticated by its token; the officer is checked in authenticate().
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
     * The HR officer the credentials belong to.
     *
     * @throws ValidationException
     */
    public function authenticate(AuditLogger $audit): User
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('username', 'password');
        $provider = Auth::guard('web')->getProvider();
        $user = $provider->retrieveByCredentials(['username' => $credentials['username']]);

        if (! $user instanceof User || ! $provider->validateCredentials($user, $credentials)) {
            RateLimiter::hit($this->throttleKey());
            $audit->record(AuditEvent::LoginFailed, __('Kiosk sign-in: :user', [
                'user' => $user instanceof User ? $user->auditLabel() : __('Unknown username'),
            ]), $user instanceof User ? $user : null);

            throw ValidationException::withMessages(['username' => trans('auth.failed')]);
        }

        if (! KioskEnrollmentSessions::mayEnroll($user)) {
            RateLimiter::hit($this->throttleKey());
            $audit->record(AuditEvent::AccessDenied, __('Kiosk sign-in refused (not allowed to enroll fingerprints): :user', ['user' => $user->auditLabel()]), $user);

            throw ValidationException::withMessages([
                'username' => $user->is_active
                    ? __('Only HR staff can enroll fingerprints.')
                    : __('Your account has been deactivated. Please contact the System Administrator.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    private function throttleKey(): string
    {
        return 'kiosk|'.Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}
