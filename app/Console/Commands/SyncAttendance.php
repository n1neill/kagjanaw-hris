<?php

namespace App\Console\Commands;

use App\Enums\SyncStatus;
use App\Models\BiometricDevice;
use App\Services\Attendance\AttendanceSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Pulls new punches from every active biometric device. Run by the scheduler
 * every few minutes (the sync interval in System Settings).
 */
#[Signature('attendance:sync {--device=* : Only sync these device IDs}')]
#[Description('Pull attendance logs from biometric devices and update Daily Time Records')]
class SyncAttendance extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AttendanceSyncService $sync): int
    {
        $devices = BiometricDevice::query()
            ->where('is_active', true)
            ->when($this->option('device'), fn ($query, array $ids) => $query->whereKey($ids))
            ->get();

        if ($devices->isEmpty()) {
            $this->info('No active biometric devices to sync.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($devices as $device) {
            $run = $sync->sync($device);

            if ($run->status === SyncStatus::Succeeded) {
                $this->info("{$device->name}: {$run->fetched_count} read, {$run->inserted_count} new, {$run->duplicate_count} duplicates, {$run->flagged_count} flagged.");
            } else {
                $failed++;
                $this->error("{$device->name}: {$run->error_message}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
