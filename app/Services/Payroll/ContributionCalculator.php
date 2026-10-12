<?php

namespace App\Services\Payroll;

use App\Enums\ContributionType;
use App\Models\DeductionRate;
use Carbon\CarbonInterface;

/**
 * Monthly GSIS, PhilHealth and Pag-IBIG contributions on the monthly basic
 * salary, from the rates in force on the date. The salary is first kept
 * within the rate's floor and ceiling (e.g. PhilHealth ₱10,000–₱100,000,
 * Pag-IBIG up to ₱10,000).
 */
class ContributionCalculator
{
    /**
     * @return array<string, array{employee: int, employer: int}> Centavos, keyed by ContributionType value.
     */
    public function monthly(int $monthlySalary, CarbonInterface $date): array
    {
        $contributions = [];

        foreach (ContributionType::cases() as $type) {
            $contributions[$type->value] = $this->forType($type, $monthlySalary, $date);
        }

        return $contributions;
    }

    /**
     * @return array{employee: int, employer: int}
     */
    public function forType(ContributionType $type, int $monthlySalary, CarbonInterface $date): array
    {
        $rate = DeductionRate::query()
            ->where('type', $type)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->orderByDesc('effective_from')
            ->first();

        if ($rate === null || $monthlySalary <= 0) {
            return ['employee' => 0, 'employer' => 0];
        }

        $base = $monthlySalary;

        if ($rate->salary_floor !== null) {
            $base = max($base, Money::toCentavos($rate->salary_floor));
        }

        if ($rate->salary_ceiling !== null) {
            $base = min($base, Money::toCentavos($rate->salary_ceiling));
        }

        return [
            'employee' => Money::percentOf($base, Money::toRateHundredths($rate->employee_rate)),
            'employer' => Money::percentOf($base, Money::toRateHundredths($rate->employer_rate)),
        ];
    }
}
