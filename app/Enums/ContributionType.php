<?php

namespace App\Enums;

/**
 * Mandatory government contributions withheld from salary-grade employees.
 */
enum ContributionType: string
{
    case Gsis = 'gsis';
    case PhilHealth = 'philhealth';
    case PagIbig = 'pagibig';

    public function label(): string
    {
        return match ($this) {
            self::Gsis => 'GSIS',
            self::PhilHealth => 'PhilHealth',
            self::PagIbig => 'Pag-IBIG (HDMF)',
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
