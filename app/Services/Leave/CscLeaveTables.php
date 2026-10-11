<?php

namespace App\Services\Leave;

/**
 * The CSC conversion tables used on the leave card (Omnibus Rules on Leave,
 * CSC MC No. 41, s. 1998, as amended). All values are in thousandths of a day.
 *
 * VERIFY AGAINST CURRENT OFFICIAL TABLES before production use.
 */
final class CscLeaveTables
{
    /**
     * Vacation and sick leave each earn 1.25 days per month of service (15 days a year).
     */
    public const MONTHLY_CREDITS = 1250;

    /**
     * Leave credits are computed on a 30-day month.
     */
    public const DAYS_PER_MONTH = 30;

    /**
     * Minutes in an 8-hour working day, the basis of the time conversion table.
     */
    public const MINUTES_PER_DAY = 480;

    /**
     * Credits earned in a month: 1.250 days for a full month, prorated by the
     * days in service (the "leave credits earned on a daily basis" table),
     * less the proportion for days of leave without pay (the "leave without
     * pay" table). Both tables are 1.25 × days ÷ 30, to three decimals.
     *
     * @param  int  $serviceDays  Calendar days in service during the month; a full month counts as 30.
     * @param  int  $withoutPayThousandths  Days of absence or leave without pay, in thousandths.
     */
    public static function earnedForMonth(int $serviceDays, int $withoutPayThousandths = 0): int
    {
        $creditableThousandths = min($serviceDays, self::DAYS_PER_MONTH) * 1000 - max(0, $withoutPayThousandths);

        if ($creditableThousandths <= 0) {
            return 0;
        }

        return self::divideRoundingHalfEven(self::MONTHLY_CREDITS * $creditableThousandths, self::DAYS_PER_MONTH * 1000);
    }

    /**
     * The fraction of a day equivalent to a number of minutes of tardiness or
     * undertime (the "conversion of working hours/minutes into fractions of a
     * day" table): 0.125 per hour, and each remaining minute ÷ 480, rounded
     * to three decimals as printed in the table (e.g. 30 min = 0.062).
     */
    public static function minutesToDays(int $minutes): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        return intdiv($minutes, 60) * 125 + self::divideRoundingHalfEven(($minutes % 60) * 1000, self::MINUTES_PER_DAY);
    }

    /**
     * Integer division rounded half to even, which reproduces the published tables.
     */
    private static function divideRoundingHalfEven(int $numerator, int $denominator): int
    {
        $quotient = intdiv($numerator, $denominator);
        $twiceRemainder = 2 * ($numerator % $denominator);

        return match (true) {
            $twiceRemainder > $denominator => $quotient + 1,
            $twiceRemainder === $denominator => $quotient + ($quotient % 2),
            default => $quotient,
        };
    }
}
