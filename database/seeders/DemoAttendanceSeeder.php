<?php

namespace Database\Seeders;

use App\Models\BiometricDevice;
use App\Services\Attendance\AttendanceProcessor;
use App\Services\Attendance\AttendanceSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * A mock time clock with attendance since the start of last month, so DTRs,
 * summaries and the sync log have data to show. Development only.
 */
class DemoAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(AttendanceSyncService $sync, AttendanceProcessor $processor): void
    {
        $device = BiometricDevice::firstOrCreate(['name' => 'Main Lobby Time Clock'], [
            'driver' => 'mock',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'location' => 'Municipal Hall, ground floor',
            'is_active' => true,
        ]);

        $since = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        $sync->sync($device, since: $since);

        $processor->processAllEmployees($since, CarbonImmutable::today());
    }
}
