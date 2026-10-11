<?php

namespace App\Services\Attendance;

use App\Models\BiometricDevice;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the driver configured for a device (config/attendance.php).
 */
class BiometricDriverManager
{
    public function __construct(private Container $container) {}

    public function driverFor(BiometricDevice $device): BiometricDriver
    {
        $class = config("attendance.drivers.{$device->driver}");

        if ($class === null) {
            throw new DeviceConnectionException("No driver named \"{$device->driver}\" is configured.");
        }

        return $this->container->make($class);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(array_keys(config('attendance.drivers')))
            ->mapWithKeys(fn (string $name): array => [$name => match ($name) {
                'mock' => 'Mock (no hardware, for testing)',
                'zkteco' => 'ZKTeco (ZKLib, TCP/IP)',
                default => $name,
            }])
            ->all();
    }
}
