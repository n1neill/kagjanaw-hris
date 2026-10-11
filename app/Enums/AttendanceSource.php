<?php

namespace App\Enums;

/**
 * Where a raw punch came from.
 */
enum AttendanceSource: string
{
    case Device = 'device';
    case CsvImport = 'csv-import';

    public function label(): string
    {
        return match ($this) {
            self::Device => 'Biometric device',
            self::CsvImport => 'CSV import',
        };
    }
}
