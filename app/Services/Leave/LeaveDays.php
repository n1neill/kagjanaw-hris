<?php

namespace App\Services\Leave;

/**
 * Leave credits are kept to three decimal places, as on the CSC leave card.
 * All arithmetic is done in whole thousandths of a day (1 day = 1000) so
 * that floating-point rounding never changes a balance.
 */
final class LeaveDays
{
    /**
     * Thousandths of a day from a stored decimal ("1.250"), a number or null.
     */
    public static function toThousandths(string|int|float|null $days): int
    {
        if ($days === null || $days === '') {
            return 0;
        }

        if (is_string($days) && preg_match('/^(-)?(\d+)(?:\.(\d{1,3}))?$/', trim($days), $matches) === 1) {
            $value = (int) $matches[2] * 1000 + (int) str_pad($matches[3] ?? '', 3, '0');

            return $matches[1] === '-' ? -$value : $value;
        }

        return (int) round((float) $days * 1000);
    }

    /**
     * The decimal string stored in the database, e.g. 1250 → "1.250".
     */
    public static function toDecimal(int $thousandths): string
    {
        $sign = $thousandths < 0 ? '-' : '';
        $absolute = abs($thousandths);

        return sprintf('%s%d.%03d', $sign, intdiv($absolute, 1000), $absolute % 1000);
    }

    /**
     * For display, e.g. "1.250", "15.000", "-0.062".
     */
    public static function format(string|int|float|null $days): string
    {
        return self::toDecimal(is_int($days) ? $days : self::toThousandths($days));
    }
}
