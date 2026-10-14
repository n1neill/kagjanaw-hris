<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * System settings the administrator maintains, with defaults used until they
 * are saved:
 *
 *  - How often the scheduler pulls logs from the biometric devices.
 *  - Whether and when the daily backup runs, and how many backups to keep.
 *  - How long audit log entries are kept.
 *
 * Office hours, holidays and payroll settings belong to HR (Work Calendar
 * and Payroll Settings).
 */
class SystemSettings
{
    public const SYNC_INTERVAL = 'attendance.sync_interval_minutes';

    public const BACKUP_ENABLED = 'backups.enabled';

    public const BACKUP_TIME = 'backups.time';

    public const BACKUPS_KEPT = 'backups.kept';

    public const AUDIT_RETENTION_YEARS = 'audit.retention_years';

    public const DEFAULT_BACKUP_TIME = '18:00';

    public const DEFAULT_BACKUPS_KEPT = 30;

    public const DEFAULT_AUDIT_RETENTION_YEARS = 5;

    /**
     * Minutes between scheduled syncs (1–59). Defaults to ATTENDANCE_SYNC_INTERVAL.
     */
    public function syncIntervalMinutes(): int
    {
        return max(1, min(59, (int) Setting::read(self::SYNC_INTERVAL, config('attendance.sync_interval_minutes'))));
    }

    /**
     * Whether the scheduled sync runs this minute (every N minutes past the hour, like cron's * /N).
     */
    public function isSyncDue(CarbonInterface $now): bool
    {
        return $now->minute % $this->syncIntervalMinutes() === 0;
    }

    public function isBackupEnabled(): bool
    {
        return (bool) Setting::read(self::BACKUP_ENABLED, true);
    }

    /**
     * The time of day of the scheduled backup, as HH:MM.
     */
    public function backupTime(): string
    {
        return (string) Setting::read(self::BACKUP_TIME, self::DEFAULT_BACKUP_TIME);
    }

    public function isBackupDue(CarbonInterface $now): bool
    {
        return $this->isBackupEnabled() && $now->format('H:i') === $this->backupTime();
    }

    /**
     * How many of the newest backups are kept when old ones are pruned.
     */
    public function backupsKept(): int
    {
        return (int) Setting::read(self::BACKUPS_KEPT, self::DEFAULT_BACKUPS_KEPT);
    }

    public function auditRetentionYears(): int
    {
        return (int) Setting::read(self::AUDIT_RETENTION_YEARS, self::DEFAULT_AUDIT_RETENTION_YEARS);
    }

    /**
     * @param  array{sync_interval_minutes: int, backup_enabled: bool, backup_time: string, backups_kept: int, audit_retention_years: int}  $values
     */
    public function update(array $values, User $user): void
    {
        Setting::write(self::SYNC_INTERVAL, $values['sync_interval_minutes'], $user);
        Setting::write(self::BACKUP_ENABLED, $values['backup_enabled'], $user);
        Setting::write(self::BACKUP_TIME, $values['backup_time'], $user);
        Setting::write(self::BACKUPS_KEPT, $values['backups_kept'], $user);
        Setting::write(self::AUDIT_RETENTION_YEARS, $values['audit_retention_years'], $user);
    }
}
