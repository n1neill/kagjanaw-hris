<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Running => 'yellow',
            self::Succeeded => 'green',
            self::Failed => 'red',
        };
    }
}
