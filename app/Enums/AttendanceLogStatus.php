<?php

namespace App\Enums;

/**
 * Whether a raw punch can be used to build daily time records.
 * Only Valid logs are processed; the others are flagged for HR.
 */
enum AttendanceLogStatus: string
{
    case Valid = 'valid';
    case UnknownUser = 'unknown-user';
    case FutureTimestamp = 'future-timestamp';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::UnknownUser => 'Unknown biometric ID',
            self::FutureTimestamp => 'Time is in the future',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
