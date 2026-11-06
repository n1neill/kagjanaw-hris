<?php

namespace App\Services\Attendance\Drivers;

use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Services\Attendance\BiometricDriver;
use App\Services\Attendance\DeviceConnectionException;
use App\Services\Attendance\DeviceInfo;
use App\Services\Attendance\ReceivesPushedPunches;
use Carbon\CarbonImmutable;

/**
 * A DigitalPersona U.are.U fingerprint reader on a kiosk PC. The reader has
 * no memory or matcher of its own, so the kiosk app matches fingerprints and
 * sends each punch to the kiosk API; there is nothing to read from it here.
 * The connection test reports whether the kiosk has checked in recently.
 */
class DigitalPersonaKioskDriver implements BiometricDriver, ReceivesPushedPunches
{
    public function testConnection(BiometricDevice $device): DeviceInfo
    {
        $offlineAfter = (int) config('attendance.kiosk.offline_after_minutes');

        if ($device->last_seen_at === null) {
            throw new DeviceConnectionException("Kiosk {$device->name} has never checked in. Generate a kiosk token and enter it in the kiosk app.");
        }

        if ($device->last_seen_at->lessThan(CarbonImmutable::now()->subMinutes($offlineAfter))) {
            throw new DeviceConnectionException("Kiosk {$device->name} last checked in {$device->last_seen_at->diffForHumans()}.");
        }

        return new DeviceInfo(
            model: 'DigitalPersona U.are.U kiosk',
            serialNumber: sprintf('KIOSK%06d', $device->id),
            firmwareVersion: 'checked in '.$device->last_seen_at->diffForHumans(),
            enrolledUsers: Employee::query()->inService()->whereHas('fingerprintTemplates')->count(),
            storedLogs: $device->attendanceLogs()->count(),
        );
    }

    /**
     * Punches arrive through the kiosk API instead.
     *
     * @return iterable<never>
     */
    public function fetchLogs(BiometricDevice $device, CarbonImmutable $since, CarbonImmutable $until): iterable
    {
        return [];
    }
}
