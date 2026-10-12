<?php

namespace App\Enums;

/**
 * Who receives an allowance: every salary-grade employee (e.g. PERA), or
 * only the employees HR assigns it to.
 */
enum AllowanceCoverage: string
{
    case AllSalaryGrade = 'all-salary-grade';
    case Assigned = 'assigned';

    public function label(): string
    {
        return match ($this) {
            self::AllSalaryGrade => 'All salary-grade employees',
            self::Assigned => 'Only employees it is assigned to',
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
