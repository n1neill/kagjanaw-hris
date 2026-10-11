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
