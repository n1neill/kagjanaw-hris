<?php

namespace App\Services\Payroll;

use App\Enums\AllowanceCoverage;
use App\Enums\PayBasis;
use App\Models\AllowanceType;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The allowances an employee receives in a date range: allowances covering
 * every salary-grade employee, plus those assigned to them. An assignment of
 * a covering allowance overrides its amount or dates for that employee.
 */
class AllowanceResolver
{
    /**
     * @var Collection<int, AllowanceType>|null
     */
    private ?Collection $activeTypes = null;

    /**
     * @return Collection<int, array{type: AllowanceType, monthly: int}> Monthly amounts in centavos.
     */
    public function for(Employee $employee, CarbonInterface $from, CarbonInterface $to): Collection
    {
        if ($employee->payBasis() !== PayBasis::SalaryGrade) {
            return new Collection;
        }

        $assignments = $employee->relationLoaded('allowances')
            ? $employee->allowances
            : $employee->allowances()->get();
        $assignments = $assignments->keyBy('allowance_type_id');

        return $this->activeTypes()
            ->map(function (AllowanceType $type) use ($assignments, $from, $to): ?array {
                /** @var EmployeeAllowance|null $assignment */
                $assignment = $assignments->get($type->id);

                if ($assignment !== null) {
                    return $assignment->overlaps($from, $to)
                        ? ['type' => $type, 'monthly' => Money::toCentavos($assignment->monthly_amount ?? $type->monthly_amount)]
                        : null;
                }

                return $type->coverage === AllowanceCoverage::AllSalaryGrade
                    ? ['type' => $type, 'monthly' => Money::toCentavos($type->monthly_amount)]
                    : null;
            })
            ->filter(fn (?array $allowance): bool => $allowance !== null && $allowance['monthly'] > 0)
            ->values();
    }

    /**
     * @return Collection<int, AllowanceType>
     */
    private function activeTypes(): Collection
    {
        return $this->activeTypes ??= AllowanceType::query()->active()->orderBy('name')->get();
    }
}
