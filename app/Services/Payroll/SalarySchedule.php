<?php

namespace App\Services\Payroll;

use App\Models\SalaryGrade;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Monthly salaries by grade and step. The LGU rate is the national Salary
 * Schedule amount in force on the date, times the percentage for the LGU's
 * income class, rounded to the nearest peso.
 */
class SalarySchedule
{
    /**
     * National amounts per tranche, memoized for a payroll computation.
     *
     * @var array<string, array<string, int>>
     */
    private array $tranches = [];

    public function __construct(private PayrollSettings $settings) {}

    /**
     * The LGU monthly salary in centavos, or null when the grade and step are
     * not in the schedule in force on the date.
     */
    public function monthlySalary(int $grade, int $step, CarbonInterface $date): ?int
    {
        $national = $this->nationalAmount($grade, $step, $date);

        return $national === null ? null : $this->lguAmount($national);
    }

    /**
     * The national Salary Schedule amount in centavos.
     */
    public function nationalAmount(int $grade, int $step, CarbonInterface $date): ?int
    {
        $effectiveFrom = $this->effectiveDateOn($date);

        if ($effectiveFrom === null) {
            return null;
        }

        $this->tranches[$effectiveFrom] ??= SalaryGrade::query()
            ->whereDate('effective_from', $effectiveFrom)
            ->get(['grade', 'step', 'amount'])
            ->mapWithKeys(fn (SalaryGrade $cell): array => [$cell->grade.'-'.$cell->step => Money::toCentavos($cell->amount)])
            ->all();

        return $this->tranches[$effectiveFrom][$grade.'-'.$step] ?? null;
    }

    /**
     * A national amount at the LGU's income-class percentage, rounded to the peso.
     */
    public function lguAmount(int $nationalCentavos): int
    {
        return Money::roundToPeso(Money::percentOf($nationalCentavos, $this->settings->salaryPercentage()));
    }

    /**
     * The effectivity date (Y-m-d) of the tranche in force on a date.
     */
    public function effectiveDateOn(CarbonInterface $date): ?string
    {
        $effectiveFrom = SalaryGrade::query()
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->max('effective_from');

        return $effectiveFrom === null ? null : substr((string) $effectiveFrom, 0, 10);
    }

    /**
     * Every tranche's effectivity date, latest first.
     *
     * @return Collection<int, string>
     */
    public function effectivityDates(): Collection
    {
        return SalaryGrade::query()
            ->select('effective_from')
            ->distinct()
            ->orderByDesc('effective_from')
            ->pluck('effective_from')
            ->map(fn ($date): string => $date->toDateString())
            ->values();
    }
}
