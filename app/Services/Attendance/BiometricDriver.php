<?php

namespace App\Services\Attendance;

use App\Models\BiometricDevice;
use Carbon\CarbonImmutable;

/**
 * A way of talking to a biometric time clock. Implementations are registered
 * in config/attendance.php and chosen per device.
 */
interface BiometricDriver
{
    /**
     * Connect to the device and report what it is.
     *
     * @throws DeviceConnectionException when the device cannot be reached.
     */
    public function testConnection(BiometricDevice $device): DeviceInfo;

    /**
     * Read the punches recorded between the two moments (inclusive).
     *
     * @return iterable<RawPunch>
     *
     * @throws DeviceConnectionException when the device cannot be reached.
     */
    public function fetchLogs(BiometricDevice $device, CarbonImmutable $since, CarbonImmutable $until): iterable;
}
