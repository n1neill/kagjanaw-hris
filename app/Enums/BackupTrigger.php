<?php

namespace App\Enums;

/**
 * Why a backup was made.
 */
enum BackupTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case BeforeRestore = 'before-restore';
    case Uploaded = 'uploaded';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Scheduled => 'Daily',
            self::BeforeRestore => 'Before a restore',
            self::Uploaded => 'Uploaded',
        };
    }
}
