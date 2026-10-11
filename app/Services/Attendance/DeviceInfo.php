<?php

namespace App\Services\Attendance;

/**
 * What a device reports about itself after a successful connection test.
 */
final readonly class DeviceInfo
{
    public function __construct(
        public string $model,
        public string $serialNumber,
        public string $firmwareVersion,
        public int $enrolledUsers,
        public int $storedLogs,
    ) {}
}
