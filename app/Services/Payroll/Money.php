<?php

namespace App\Services\Payroll;

/**
 * Peso amounts are stored as DECIMAL(…, 2) and computed in whole centavos
 * (₱1.00 = 100) so that floating-point rounding never changes pay. Rates
 * are kept as percentages with two decimals (9.00% = 900 hundredths).
 * Every division rounds half up, as in manual payroll computation.
 */
final class Money
{
    /**
     * Centavos from a stored decimal ("1234.50"), a number or null.
     */
    public static function toCentavos(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        if (is_string($amount) && preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', trim($amount), $matches) === 1) {
            $value = (int) $matches[2] * 100 + (int) str_pad($matches[3] ?? '', 2, '0');

            return $matches[1] === '-' ? -$value : $value;
        }

        return (int) round((float) $amount * 100);
    }

    /**
     * The decimal string stored in the database, e.g. 123450 → "1234.50".
     */
    public static function toDecimal(int $centavos): string
    {
        $sign = $centavos < 0 ? '-' : '';
        $absolute = abs($centavos);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }

    /**
     * For display, e.g. "1,234.50" or "-0.25".
     */
    public static function format(string|int|float|null $amount): string
    {
        $centavos = is_int($amount) ? $amount : self::toCentavos($amount);
        $sign = $centavos < 0 ? '-' : '';
        $absolute = abs($centavos);

        return $sign.number_format(intdiv($absolute, 100)).sprintf('.%02d', $absolute % 100);
    }

    /**
     * Hundredths of a percent from a stored rate ("9.00" → 900).
     */
    public static function toRateHundredths(string|int|float|null $percent): int
    {
        return self::toCentavos($percent);
    }

    /**
     * A percentage of an amount, e.g. percentOf(1_000_000, 900) = 9% of ₱10,000.00.
     */
    public static function percentOf(int $centavos, int $rateHundredths): int
    {
        return self::ratio($centavos, $rateHundredths, 10_000);
    }

    /**
     * amount × numerator ÷ denominator, rounded half up (away from zero).
     */
    public static function ratio(int $centavos, int $numerator, int $denominator): int
    {
        if ($denominator === 0) {
            return 0;
        }

        $product = $centavos * $numerator;
        $sign = ($product < 0) !== ($denominator < 0) ? -1 : 1;
        $product = abs($product);
        $denominator = abs($denominator);

        return $sign * intdiv($product * 2 + $denominator, $denominator * 2);
    }

    /**
     * Round to the nearest whole peso, half up.
     */
    public static function roundToPeso(int $centavos): int
    {
        return self::ratio($centavos, 1, 100) * 100;
    }

    /**
     * The two semi-monthly shares of a monthly amount: the first half gets
     * the lower half, the second half the rest, so the two always add up.
     *
     * @return array{1: int, 2: int}
     */
    public static function splitMonthly(int $monthlyCentavos): array
    {
        $first = intdiv($monthlyCentavos, 2);

        return [1 => $first, 2 => $monthlyCentavos - $first];
    }
}
