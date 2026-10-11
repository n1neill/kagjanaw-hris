<?php

namespace App\Enums;

/**
 * Philippine holiday classifications. All are non-working days for the DTR;
 * the type matters for holiday pay rules in payroll.
 */
enum HolidayType: string
{
    case Regular = 'regular';
    case SpecialNonWorking = 'special-non-working';
    case Local = 'local';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular holiday',
            self::SpecialNonWorking => 'Special non-working day',
            self::Local => 'Local holiday',
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
