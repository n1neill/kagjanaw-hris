<?php

use App\Services\Attendance\Drivers\MockBiometricDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Biometric Device Drivers
    |--------------------------------------------------------------------------
    |
    | Each biometric device record names one of these drivers. "mock" generates
    | realistic punches so the whole attendance flow can be developed and
    | demonstrated without hardware. A ZKTeco (ZKLib) driver is added here
    | once a device is available; it must implement
    | App\Services\Attendance\BiometricDriver.
    |
    */

    'default_driver' => env('ATTENDANCE_DRIVER', 'mock'),

    'drivers' => [
        'mock' => MockBiometricDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronization
    |--------------------------------------------------------------------------
    |
    | How often the scheduler pulls logs from active devices, and how far back
    | each sync re-reads before the last successful sync. Re-reading is safe:
    | duplicates are skipped by a unique index on (biometric ID, time).
    |
    */

    'sync_interval_minutes' => (int) env('ATTENDANCE_SYNC_INTERVAL', 5),

    'sync_overlap_hours' => 24,

    /**
     * On the first sync of a device, read at most this many days back.
     */
    'initial_sync_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Punch Handling
    |--------------------------------------------------------------------------
    |
    | Repeated taps within this many minutes of an employee's previous punch
    | are treated as one punch.
    |
    */

    'duplicate_tap_minutes' => 5,

];
