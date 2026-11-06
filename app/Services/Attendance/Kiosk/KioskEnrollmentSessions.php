<?php

namespace App\Services\Attendance\Kiosk;

use App\Enums\Permission;
use App\Models\BiometricDevice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Short sign-ins of HR staff at a kiosk, for enrolling fingerprints. The
 * kiosk holds a random session token; the cache holds only its hash, the
 * user and the kiosk it was issued to.
 */
class KioskEnrollmentSessions
{
    private const CACHE_PREFIX = 'kiosk-enrollment:';

    /**
     * @return array{token: string, expires_at: CarbonImmutable}
     */
    public function start(BiometricDevice $device, User $user): array
    {
        $token = Str::random(48);
        $expiresAt = CarbonImmutable::now()->addMinutes((int) config('attendance.kiosk.enrollment_session_minutes'));

        Cache::put($this->key($token), ['user_id' => $user->id, 'device_id' => $device->id], $expiresAt);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * The HR user signed in with the token at this kiosk, if they still may enroll.
     */
    public function user(BiometricDevice $device, string $token): ?User
    {
        if ($token === '') {
            return null;
        }

        $session = Cache::get($this->key($token));

        if (! is_array($session) || $session['device_id'] !== $device->id) {
            return null;
        }

        $user = User::query()->find($session['user_id']);

        return $user !== null && self::mayEnroll($user) ? $user : null;
    }

    public function end(string $token): void
    {
        Cache::forget($this->key($token));
    }

    /**
     * Fingerprints are enrolled by HR staff with an active account.
     */
    public static function mayEnroll(User $user): bool
    {
        return $user->is_active && $user->can(Permission::ManageEmployees);
    }

    private function key(string $token): string
    {
        return self::CACHE_PREFIX.hash('sha256', $token);
    }
}
