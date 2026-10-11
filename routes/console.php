<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Attendance
|--------------------------------------------------------------------------
|
| Requires `php artisan schedule:run` every minute (Windows Task Scheduler
| or cron); see the README.
|
*/

$syncInterval = max(1, min(59, (int) config('attendance.sync_interval_minutes')));

Schedule::command('attendance:sync')
    ->cron("*/{$syncInterval} * * * *")
    ->withoutOverlapping();

Schedule::command('attendance:process')
    ->dailyAt('00:15')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Leave
|--------------------------------------------------------------------------
|
| Last month's leave credits are posted for employees whose DTR has been
| validated (also done the moment HR validates a month). Unused forced leave
| is forfeited in mid-January, after December leave has been approved.
|
*/

Schedule::command('leave:post-credits')
    ->dailyAt('01:30')
    ->withoutOverlapping();

Schedule::command('leave:forfeit-forced-leave')
    ->yearlyOn(1, 15, '02:00')
    ->withoutOverlapping();
