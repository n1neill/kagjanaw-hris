<?php

namespace App\Enums;

/**
 * Which credits a leave type is charged against.
 */
enum LeaveCreditSource: string
{
    /**
     * Vacation leave credits; the excess is leave without pay.
     */
    case VacationCredits = 'vacation-credits';

    /**
     * Sick leave credits, then vacation leave credits; the excess is leave without pay.
     */
    case SickCredits = 'sick-credits';

    /**
     * A yearly allotment of its own (e.g. 3 days of Special Privilege Leave)
     * that cannot be exceeded and does not carry over.
     */
    case AnnualAllotment = 'annual-allotment';

    /**
     * Granted by law per occasion (e.g. 105 days of Maternity Leave); not
     * charged against any credits.
     */
    case NotCharged = 'not-charged';

    public function label(): string
    {
        return match ($this) {
            self::VacationCredits => 'Vacation leave credits',
            self::SickCredits => 'Sick leave credits, then vacation leave credits',
            self::AnnualAllotment => 'Own yearly allotment',
            self::NotCharged => 'Not charged to leave credits',
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
