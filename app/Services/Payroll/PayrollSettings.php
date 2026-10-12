<?php

namespace App\Services\Payroll;

use App\Models\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Payroll settings HR maintains, with defaults used until they are saved.
 *
 *  - LGU income class and the percentage of the national Salary Schedule
 *    paid for each class (DBM Local Budget Circulars; municipalities:
 *    1st 90%, 2nd 85%, 3rd 80%, 4th 75%, 5th 70%). The default class (5th)
 *    is a placeholder until HR confirms the municipality's class.
 *  - Minimum monthly net take-home pay protected when deducting loans.
 *  - Tax withheld from Job Order / Contract of Service pay.
 *  - The first attendance month whose absences payroll deducts.
 */
class PayrollSettings
{
    public const INCOME_CLASS = 'payroll.income_class';

    public const INCOME_CLASS_CONFIRMED = 'payroll.income_class_confirmed';

    public const INCOME_CLASS_PERCENTAGES = 'payroll.income_class_percentages';

    public const MINIMUM_NET_PAY = 'payroll.minimum_net_pay';

    public const DAILY_RATE_TAX_RATE = 'payroll.daily_rate_tax_rate';

    public const ATTENDANCE_DEDUCTIONS_FROM = 'payroll.attendance_deductions_from';

    /**
     * @var array<int, string>
     */
    public const DEFAULT_PERCENTAGES = [1 => '90.00', 2 => '85.00', 3 => '80.00', 4 => '75.00', 5 => '70.00'];

    public const DEFAULT_INCOME_CLASS = 5;

    public const DEFAULT_MINIMUM_NET_PAY = '5000.00';

    public const DEFAULT_DAILY_RATE_TAX_RATE = '5.00';

    /**
     * "1st class", "2nd class" and so on.
     */
    public static function classLabel(int $class): string
    {
        return match ($class) {
            1 => __('1st class'),
            2 => __('2nd class'),
            3 => __('3rd class'),
            default => __(':nth class', ['nth' => $class.'th']),
        };
    }

    /**
     * @return array<int, string>
     */
    public static function classOptions(): array
    {
        return collect(array_keys(self::DEFAULT_PERCENTAGES))
            ->mapWithKeys(fn (int $class): array => [$class => self::classLabel($class)])
            ->all();
    }

    public function incomeClass(): int
    {
        return (int) Setting::read(self::INCOME_CLASS, self::DEFAULT_INCOME_CLASS);
    }

    public function isIncomeClassConfirmed(): bool
    {
        return (bool) Setting::read(self::INCOME_CLASS_CONFIRMED, false);
    }

    /**
     * @return array<int, string> Percent of the national Salary Schedule by income class.
     */
    public function percentages(): array
    {
        $saved = Setting::read(self::INCOME_CLASS_PERCENTAGES, []);
        $percentages = self::DEFAULT_PERCENTAGES;

        foreach ($saved as $class => $percent) {
            $percentages[(int) $class] = (string) $percent;
        }

        return $percentages;
    }

    /**
     * The percentage of the national Salary Schedule the LGU pays, in hundredths (70.00% = 7000).
     */
    public function salaryPercentage(): int
    {
        return Money::toRateHundredths($this->percentages()[$this->incomeClass()] ?? '100.00');
    }

    /**
     * Minimum monthly net take-home pay, in centavos.
     */
    public function minimumNetPay(): int
    {
        return Money::toCentavos((string) Setting::read(self::MINIMUM_NET_PAY, self::DEFAULT_MINIMUM_NET_PAY));
    }

    /**
     * Tax withheld from daily-rate pay, in hundredths of a percent.
     */
    public function dailyRateTaxRate(): int
    {
        return Money::toRateHundredths((string) Setting::read(self::DAILY_RATE_TAX_RATE, self::DEFAULT_DAILY_RATE_TAX_RATE));
    }

    /**
     * The first attendance month payroll deducts absences for. Until HR sets
     * it, the month before the first payroll period in the system, so the
     * first payroll deducts the previous month and nothing earlier.
     */
    public function attendanceDeductionsFrom(): ?CarbonImmutable
    {
        $saved = Setting::read(self::ATTENDANCE_DEDUCTIONS_FROM);

        if ($saved !== null) {
            return CarbonImmutable::createFromFormat('!Y-m', (string) $saved);
        }

        $first = PayrollPeriod::query()->orderBy('start_date')->value('start_date');

        return $first === null ? null : CarbonImmutable::parse($first)->startOfMonth()->subMonthNoOverflow();
    }

    public function savedAttendanceDeductionsFrom(): ?string
    {
        return Setting::read(self::ATTENDANCE_DEDUCTIONS_FROM);
    }

    /**
     * @param  array{income_class: int, income_class_confirmed: bool, percentages: array<int, string>, minimum_net_pay: string, daily_rate_tax_rate: string, attendance_deductions_from: ?string}  $values
     */
    public function update(array $values, User $user): void
    {
        Setting::write(self::INCOME_CLASS, $values['income_class'], $user);
        Setting::write(self::INCOME_CLASS_CONFIRMED, $values['income_class_confirmed'], $user);
        Setting::write(self::INCOME_CLASS_PERCENTAGES, $values['percentages'], $user);
        Setting::write(self::MINIMUM_NET_PAY, $values['minimum_net_pay'], $user);
        Setting::write(self::DAILY_RATE_TAX_RATE, $values['daily_rate_tax_rate'], $user);
        Setting::write(self::ATTENDANCE_DEDUCTIONS_FROM, $values['attendance_deductions_from'], $user);
    }
}
