<?php

namespace App\Enums;

/**
 * How an employment type is paid, which also decides the payroll it is in:
 * plantilla, casual, contractual and elective personnel by salary grade and
 * step; Job Order and Contract of Service workers by a daily rate for the
 * time actually worked.
 */
enum PayBasis: string
{
    case SalaryGrade = 'salary-grade';
    case DailyRate = 'daily-rate';

    public function label(): string
    {
        return match ($this) {
            self::SalaryGrade => 'Salary grade and step (monthly)',
            self::DailyRate => 'Daily rate (Job Order / Contract of Service)',
        };
    }

    /**
     * The name of the payroll for this pay basis.
     */
    public function payrollLabel(): string
    {
        return match ($this) {
            self::SalaryGrade => 'Regular payroll',
            self::DailyRate => 'Job Order / COS payroll',
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

    /**
     * @return array<string, string>
     */
    public static function payrollOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->payrollLabel()])
            ->all();
    }
}
