<?php

namespace App\Services\Attendance\Drivers;

use App\Enums\VerifyType;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\Holiday;
use App\Services\Attendance\BiometricDriver;
use App\Services\Attendance\DeviceConnectionException;
use App\Services\Attendance\DeviceInfo;
use App\Services\Attendance\RawPunch;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A pretend time clock for development and demos. It "enrolls" every
 * employee in service who has a biometric user ID and produces realistic,
 * repeatable punches on working days: mostly on time, with some late
 * arrivals, early departures, absences, missing punches and double taps.
 * It also reports punches from user 99999, who is not enrolled in the HRIS,
 * so unknown-ID flagging can be seen.
 *
 * A device whose IP address is 0.0.0.0 behaves as unreachable.
 */
class MockBiometricDriver implements BiometricDriver
{
    public const UNKNOWN_USER_ID = '99999';

    public function testConnection(BiometricDevice $device): DeviceInfo
    {
        $this->ensureReachable($device);

        return new DeviceInfo(
            model: 'Mock ZKTeco K40',
            serialNumber: sprintf('MOCK%06d', $device->id),
            firmwareVersion: 'Ver 6.60 (mock)',
            enrolledUsers: $this->enrolledUserIds()->count(),
            storedLogs: $device->attendanceLogs()->count(),
        );
    }

    /**
     * @return iterable<RawPunch>
     */
    public function fetchLogs(BiometricDevice $device, CarbonImmutable $since, CarbonImmutable $until): iterable
    {
        $this->ensureReachable($device);

        $holidays = Holiday::query()
            ->whereBetween('date', [$since->toDateString(), $until->toDateString()])
            ->pluck('date')
            ->map(fn ($date): string => $date->toDateString())
            ->all();

        $userIds = [...$this->enrolledUserIds()->all(), self::UNKNOWN_USER_ID];

        foreach (CarbonPeriod::create($since->startOfDay(), $until->startOfDay()) as $day) {
            if ($day->isWeekend() || in_array($day->toDateString(), $holidays, true)) {
                continue;
            }

            foreach ($userIds as $userId) {
                foreach ($this->punchesFor((string) $userId, CarbonImmutable::parse($day)) as $loggedAt) {
                    if ($loggedAt->betweenIncluded($since, $until)) {
                        yield new RawPunch((string) $userId, $loggedAt, VerifyType::Fingerprint);
                    }
                }
            }
        }
    }

    /**
     * The same user and day always produce the same punches, so repeated
     * syncs return identical logs (exercising duplicate detection).
     *
     * @return list<CarbonImmutable>
     */
    private function punchesFor(string $userId, CarbonImmutable $day): array
    {
        $random = new Randomizer(new Mt19937(crc32($userId.'|'.$day->toDateString())));

        $between = function (string $earliest, string $latest) use ($day, $random): CarbonImmutable {
            [$fromHour, $fromMinute] = array_map('intval', explode(':', $earliest));
            [$toHour, $toMinute] = array_map('intval', explode(':', $latest));
            $start = $fromHour * 60 + $fromMinute;

            return $day->startOfDay()->addMinutes($random->getInt($start, $toHour * 60 + $toMinute));
        };

        // 1-3: absent, 4-13: late, 14-18: leaves early, 19-21: forgets to punch out, 97-100: double tap.
        $scenario = $random->getInt(1, 100);

        if ($scenario <= 3) {
            return [];
        }

        $isLate = $scenario >= 4 && $scenario <= 13;
        $leavesEarly = $scenario >= 14 && $scenario <= 18;
        $forgetsToPunchOut = $scenario >= 19 && $scenario <= 21;

        $amIn = $isLate ? $between('08:16', '08:55') : $between('07:35', '08:14');

        $punches = [
            $amIn,
            $between('12:00', '12:10'),
            $between('12:40', '12:59'),
            $leavesEarly ? $between('16:00', '16:50') : $between('17:00', '17:25'),
        ];

        if ($forgetsToPunchOut) {
            array_pop($punches);
        }

        if ($scenario >= 97) {
            $punches[] = $amIn->addMinute();
        }

        return $punches;
    }

    /**
     * @return Collection<int, string>
     */
    private function enrolledUserIds(): Collection
    {
        return Employee::query()->inService()->whereNotNull('biometric_user_id')->pluck('biometric_user_id');
    }

    private function ensureReachable(BiometricDevice $device): void
    {
        if ($device->ip_address === '0.0.0.0') {
            throw new DeviceConnectionException("Device {$device->name} at {$device->ip_address}:{$device->port} did not respond.");
        }
    }
}
