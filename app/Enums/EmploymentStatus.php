<?php

namespace App\Enums;

/**
 * Whether an employee is in service. Any status other than Active is a
 * separation, which also deactivates the employee's login account.
 */
enum EmploymentStatus: string
{
    case Active = 'active';
    case Resigned = 'resigned';
    case Retired = 'retired';
    case Terminated = 'terminated';
    case EndOfContract = 'end-of-contract';
    case Deceased = 'deceased';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Resigned => 'Resigned',
            self::Retired => 'Retired',
            self::Terminated => 'Terminated',
            self::EndOfContract => 'End of Contract',
            self::Deceased => 'Deceased',
        };
    }

    public function isInService(): bool
    {
        return $this === self::Active;
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
