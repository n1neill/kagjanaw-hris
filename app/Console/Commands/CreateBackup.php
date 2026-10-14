<?php

namespace App\Console\Commands;

use App\Enums\BackupTrigger;
use App\Services\Backup\BackupException;
use App\Services\Backup\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Backs up the database and uploaded files. Run daily by the scheduler at
 * the time set in System Settings; old backups are then pruned.
 */
#[Signature('backup:create {--scheduled : Mark the backup as the daily backup}')]
#[Description('Back up the database and uploaded files to storage/app/backups')]
class CreateBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BackupService $backups): int
    {
        try {
            $backup = $backups->create($this->option('scheduled') ? BackupTrigger::Scheduled : BackupTrigger::Manual);
        } catch (BackupException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup created: {$backup->name} ({$backup->readableSize()}).");

        return self::SUCCESS;
    }
}
