<?php

namespace App\Console\Commands;

use App\Services\Backup\Backup;
use App\Services\Backup\BackupException;
use App\Services\Backup\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Restores a backup from storage/app/backups, replacing the database and the
 * uploaded files. A safety backup of the current state is taken first.
 */
#[Signature('backup:restore {name? : The backup file name; lists the backups when left out} {--force : Do not ask for confirmation}')]
#[Description('Restore the database and uploaded files from a backup')]
class RestoreBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BackupService $backups): int
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->table(
                ['Name', 'Created', 'By', 'Size'],
                $backups->all()->map(fn (Backup $backup): array => [$backup->name, $backup->createdAt->format('Y-m-d H:i'), $backup->createdBy ?? '—', $backup->readableSize()])->all(),
            );

            return self::SUCCESS;
        }

        $backup = $backups->find($name);

        if ($backup === null) {
            $this->error("Backup {$name} was not found in storage/app/backups.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Replace ALL current data and uploaded files with {$backup->name} ({$backup->createdAt->format('Y-m-d H:i')})?")) {
            return self::FAILURE;
        }

        try {
            $backups->restore($backup);
        } catch (BackupException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Restored {$backup->name}.");

        return self::SUCCESS;
    }
}
