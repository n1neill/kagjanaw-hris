<?php

namespace Tests\Fakes;

use App\Models\BiometricDevice;
use App\Services\Attendance\BiometricDriver;
use App\Services\Attendance\DeviceConnectionException;
use App\Services\Attendance\DeviceInfo;
use App\Services\Attendance\RawPunch;
use Carbon\CarbonImmutable;

/**
 * A test double for a time clock. Tests register it with
 * FakeBiometricDriver::register() and decide what it returns.
 */
class FakeBiometricDriver implements BiometricDriver
{
    /**
     * @var list<RawPunch>
     */
    public static array $punches = [];

    public static ?string $failure = null;

    /**
     * @var list<array{since: CarbonImmutable, until: CarbonImmutable}>
     */
    public static array $requests = [];

    /**
     * @param  list<RawPunch>  $punches
     */
    public static function register(array $punches = [], ?string $failure = null): void
    {
        static::$punches = $punches;
        static::$failure = $failure;
        static::$requests = [];

        config(['attendance.drivers.fake' => static::class]);
    }

    public function testConnection(BiometricDevice $device): DeviceInfo
    {
        $this->failIfConfigured();

        return new DeviceInfo('Fake K40', 'FAKE0001', 'Ver 6.60', 12, count(static::$punches));
    }

    /**
     * @return iterable<RawPunch>
     */
    public function fetchLogs(BiometricDevice $device, CarbonImmutable $since, CarbonImmutable $until): iterable
    {
        static::$requests[] = ['since' => $since, 'until' => $until];

        $this->failIfConfigured();

        return static::$punches;
    }

    private function failIfConfigured(): void
    {
        if (static::$failure !== null) {
            throw new DeviceConnectionException(static::$failure);
        }
    }
}
