<?php

namespace App\Services\Attendance;

use App\Enums\VerifyType;
use Carbon\CarbonImmutable;

/**
 * One punch as read from a device: who (the device's user number), when, and how.
 */
final readonly class RawPunch
{
    public function __construct(
        public string $biometricUserId,
        public CarbonImmutable $loggedAt,
        public VerifyType $verifyType = VerifyType::Unknown,
    ) {}
}
