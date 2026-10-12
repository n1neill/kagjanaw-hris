<?php

namespace App\Services\Payroll;

use App\Models\TaxBracket;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Withholding tax on compensation from the BIR monthly table in force on the
 * date: the bracket's base tax plus its rate on the excess over its lower
 * bound. Year-end annualization is done outside the system.
 */
class WithholdingTaxCalculator
{
    /**
     * Brackets per effectivity date, memoized for a payroll computation.
     *
     * @var array<string, Collection<int, TaxBracket>>
     */
    private array $tables = [];

    /**
     * Monthly tax in centavos on monthly taxable compensation in centavos.
     */
    public function monthlyTax(int $taxableCompensation, CarbonInterface $date): int
    {
        if ($taxableCompensation <= 0) {
            return 0;
        }

        $bracket = $this->table($date)
            ->filter(fn (TaxBracket $bracket): bool => $taxableCompensation > Money::toCentavos($bracket->over))
            ->last();

        if ($bracket === null) {
            return 0;
        }

        $excess = $taxableCompensation - Money::toCentavos($bracket->over);

        return Money::toCentavos($bracket->base_tax) + Money::percentOf($excess, Money::toRateHundredths($bracket->rate));
    }

    /**
     * Tax for one semi-monthly half: the monthly table applied to twice the
     * half's taxable compensation, halved. With equal halves this is exactly
     * half the monthly tax.
     */
    public function semiMonthlyTax(int $taxableCompensation, CarbonInterface $date): int
    {
        return Money::ratio($this->monthlyTax($taxableCompensation * 2, $date), 1, 2);
    }

    /**
     * @return Collection<int, TaxBracket> Sorted by lower bound.
     */
    private function table(CarbonInterface $date): Collection
    {
        $effectiveFrom = TaxBracket::query()
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->max('effective_from');

        if ($effectiveFrom === null) {
            return new Collection;
        }

        $key = substr((string) $effectiveFrom, 0, 10);

        return $this->tables[$key] ??= TaxBracket::query()
            ->whereDate('effective_from', $key)
            ->orderBy('over')
            ->get();
    }
}
