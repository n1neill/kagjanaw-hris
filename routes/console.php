<?php

use App\Models\AuditLog;
use App\Services\SystemSettings;
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
| or cron); see the README. The sync interval is a System Setting, read when
| the scheduler runs (every N minutes past the hour).
|
*/

Schedule::command('attendance:sync')
    ->everyMinute()
    ->when(fn (SystemSettings $settings): bool => $settings->isSyncDue(now()))
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

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
|
| The daily backup runs at the time set in System Settings (if enabled).
| Audit log entries older than the retention period are pruned nightly.
|
*/

Schedule::command('backup:create --scheduled')
    ->everyMinute()
    ->when(fn (SystemSettings $settings): bool => $settings->isBackupDue(now()))
    ->withoutOverlapping();

Schedule::command('model:prune', ['--model' => [AuditLog::class]])
    ->dailyAt('03:00');
