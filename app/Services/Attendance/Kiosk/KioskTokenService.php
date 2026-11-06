<?php

namespace App\Services\Attendance\Kiosk;

use App\Models\BiometricDevice;
use Illuminate\Support\Str;

/**
 * The secret a kiosk PC sends with every request. Only its SHA-256 hash is
 * stored, so the plain token is shown to the administrator once, on issue.
 */
class KioskTokenService
{
    public function issue(BiometricDevice $device): string
    {
        $token = Str::random(48);

        $device->forceFill(['api_token_hash' => $this->hash($token)])->save();

        return $token;
    }

    public function findDevice(string $token): ?BiometricDevice
    {
        if ($token === '') {
            return null;
        }

        return BiometricDevice::query()->where('api_token_hash', $this->hash($token))->first();
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
