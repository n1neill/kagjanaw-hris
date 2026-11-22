<?php

namespace App\Services\Approvals\Handlers;

use App\Enums\AuditEvent;
use App\Models\AllowanceType;
use App\Models\DeductionRate;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\PayrollRun;
use App\Models\SalaryGrade;
use App\Models\TaxBracket;
use App\Services\Approvals\ChangeCannotBeApplied;
use App\Services\Approvals\ChangeContext;
use App\Services\Audit\AuditLogger;
use App\Services\Payroll\Money;
use App\Services\Payroll\PayrollRunService;
use App\Services\Payroll\PayrollSettings;
use App\Services\Payroll\SalarySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies changes to payroll setup (allowances, deductions, contribution
 * rates, the tax table, the Salary Schedule and settings) and deletes
 * unapproved runs. Approving and releasing a run is not a change request:
 * only an HR Officer can do it (see PayrollRunPolicy).
 */
class PayrollChanges
{
    public function __construct(
        private PayrollRunService $runs,
        private PayrollSettings $settings,
        private SalarySchedule $schedule,
        private AuditLogger $audit,
    ) {}

    public function deletePayrollRun(ChangeContext $change): void
    {
        $this->runs->delete($change->subject(PayrollRun::class));
    }

    public function createAllowanceType(ChangeContext $change): AllowanceType
    {
        return AllowanceType::create($change->payload);
    }

    public function updateAllowanceType(ChangeContext $change): AllowanceType
    {
        $type = $change->subject(AllowanceType::class);
        $type->update($change->payload);

        return $type;
    }

    public function assignAllowance(ChangeContext $change): EmployeeAllowance
    {
        return $change->subject(Employee::class)->allowances()->create($change->payload);
    }

    /**
     * @return string The name of the allowance removed.
     */
    public function removeAllowance(ChangeContext $change): string
    {
        $allowance = $change->subject(EmployeeAllowance::class);
        $name = $allowance->allowanceType()->value('name');
        $allowance->delete();

        return (string) $name;
    }

    public function addDeduction(ChangeContext $change): EmployeeDeduction
    {
        $deduction = $change->subject(Employee::class)->payrollDeductions()->make($change->payload);
        $deduction->forceFill(['created_by' => $change->author->id])->save();

        return $deduction;
    }

    public function updateDeduction(ChangeContext $change): EmployeeDeduction
    {
        $deduction = $change->subject(EmployeeDeduction::class);
        $deduction->update($change->payload);

        return $deduction;
    }

    public function createContributionRate(ChangeContext $change): DeductionRate
    {
        return DeductionRate::create($change->payload);
    }

    public function updateContributionRate(ChangeContext $change): DeductionRate
    {
        $rate = $change->subject(DeductionRate::class);
        $rate->update($change->payload);

        return $rate;
    }

    /**
     * Start a new tax table as a copy of the one in force on its date.
     *
     * @return string The new table's effectivity date.
     */
    public function createTaxTable(ChangeContext $change): string
    {
        $effectiveFrom = (string) $change->input('effective_from');

        if (TaxBracket::effectivityDates()->contains($effectiveFrom)) {
            throw ValidationException::withMessages(['effective_from' => __('A tax table effective on that date already exists.')]);
        }

        $source = TaxBracket::query()->whereDate('effective_from', '<=', $effectiveFrom)->max('effective_from')
            ?? TaxBracket::query()->max('effective_from');

        DB::transaction(function () use ($source, $effectiveFrom): void {
            TaxBracket::query()
                ->when($source, fn ($query) => $query->whereDate('effective_from', substr((string) $source, 0, 10)))
                ->get()
                ->each(fn (TaxBracket $bracket) => TaxBracket::create([
                    'effective_from' => $effectiveFrom,
                    'over' => $bracket->over,
                    'base_tax' => $bracket->base_tax,
                    'rate' => $bracket->rate,
                ]));
        });

        $this->audit->record(AuditEvent::Created, __('Withholding tax table effective :date', ['date' => $effectiveFrom]), newValues: $this->brackets($effectiveFrom));

        return $effectiveFrom;
    }

    public function updateTaxTable(ChangeContext $change): void
    {
        $effectiveFrom = $this->existingTaxTable($change);
        $before = $this->brackets($effectiveFrom);

        DB::transaction(function () use ($change, $effectiveFrom): void {
            TaxBracket::query()->whereDate('effective_from', $effectiveFrom)->delete();

            foreach ($change->input('brackets', []) as $bracket) {
                TaxBracket::create([...$bracket, 'effective_from' => $effectiveFrom, 'legal_basis' => $change->input('legal_basis')]);
            }
        });

        $this->audit->record(AuditEvent::Updated, __('Withholding tax table effective :date', ['date' => $effectiveFrom]), oldValues: $before, newValues: $this->brackets($effectiveFrom));
    }

    public function deleteTaxTable(ChangeContext $change): void
    {
        $effectiveFrom = $this->existingTaxTable($change);

        if (TaxBracket::effectivityDates()->count() === 1) {
            throw ValidationException::withMessages(['effective_from' => __('The only tax table cannot be removed. Add a replacement first.')]);
        }

        $before = $this->brackets($effectiveFrom);

        TaxBracket::query()->whereDate('effective_from', $effectiveFrom)->delete();

        $this->audit->record(AuditEvent::Deleted, __('Withholding tax table effective :date', ['date' => $effectiveFrom]), oldValues: $before);
    }

    /**
     * Start a new Salary Schedule tranche as a copy of the one in force on its date.
     *
     * @return string The new tranche's effectivity date.
     */
    public function createSalaryTranche(ChangeContext $change): string
    {
        $effectiveFrom = (string) $change->input('effective_from');

        if ($this->schedule->effectivityDates()->contains($effectiveFrom)) {
            throw ValidationException::withMessages(['effective_from' => __('A tranche effective on that date already exists.')]);
        }

        $source = $this->schedule->effectiveDateOn(CarbonImmutable::parse($effectiveFrom)) ?? $this->schedule->effectivityDates()->first();
        $now = now();

        SalaryGrade::insert(SalaryGrade::query()
            ->when($source, fn ($query) => $query->whereDate('effective_from', $source))
            ->get()
            ->map(fn (SalaryGrade $cell): array => [
                'effective_from' => $effectiveFrom,
                'grade' => $cell->grade,
                'step' => $cell->step,
                'amount' => $cell->amount,
                'legal_basis' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all());

        $this->audit->record(AuditEvent::Created, __('Salary Schedule tranche effective :date', ['date' => $effectiveFrom]), newValues: ['copied_from' => $source]);

        return $effectiveFrom;
    }

    public function updateSalaryTranche(ChangeContext $change): void
    {
        $effectiveFrom = (string) $change->input('effective_from');

        if (! $this->schedule->effectivityDates()->contains($effectiveFrom)) {
            throw new ChangeCannotBeApplied(__('The Salary Schedule tranche effective :date no longer exists.', ['date' => $effectiveFrom]));
        }

        $before = $this->cells($effectiveFrom);

        DB::transaction(function () use ($change, $effectiveFrom): void {
            SalaryGrade::query()->whereDate('effective_from', $effectiveFrom)->delete();
            $now = now();
            $rows = [];

            foreach ($change->input('amounts', []) as $grade => $steps) {
                foreach ($steps as $step => $amount) {
                    if ($amount === null || $amount === '' || (int) $grade < 1 || (int) $grade > 33 || (int) $step < 1 || (int) $step > 8) {
                        continue;
                    }

                    $rows[] = [
                        'effective_from' => $effectiveFrom,
                        'grade' => (int) $grade,
                        'step' => (int) $step,
                        'amount' => Money::toDecimal(Money::toCentavos((string) $amount)),
                        'legal_basis' => $change->input('legal_basis'),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            SalaryGrade::insert($rows);
        });

        $after = $this->cells($effectiveFrom);
        $changed = array_keys(array_diff_assoc($after, $before) + array_diff_assoc($before, $after));

        $this->audit->record(
            AuditEvent::Updated,
            __('Salary Schedule tranche effective :date', ['date' => $effectiveFrom]),
            oldValues: array_intersect_key($before, array_flip($changed)),
            newValues: array_intersect_key($after, array_flip($changed)),
        );
    }

    public function updatePayrollSettings(ChangeContext $change): void
    {
        $this->settings->update($change->payload, $change->author);
    }

    private function existingTaxTable(ChangeContext $change): string
    {
        $effectiveFrom = (string) $change->input('effective_from');

        if (! TaxBracket::effectivityDates()->contains($effectiveFrom)) {
            throw new ChangeCannotBeApplied(__('The tax table effective :date no longer exists.', ['date' => $effectiveFrom]));
        }

        return $effectiveFrom;
    }

    /**
     * The table's brackets in words, plus its legal basis, for the audit log.
     *
     * @return array<string, ?string>
     */
    private function brackets(string $effectiveFrom): array
    {
        $brackets = TaxBracket::query()->whereDate('effective_from', $effectiveFrom)->orderBy('over')->get();

        return [
            'legal_basis' => $brackets->first()?->legal_basis,
            ...$brackets->mapWithKeys(fn (TaxBracket $bracket): array => ['over '.$bracket->over => $bracket->base_tax.' + '.$bracket->rate.'%'])->all(),
        ];
    }

    /**
     * The tranche as flat "SG grade-step" => amount pairs, plus its legal basis, for the audit log.
     *
     * @return array<string, ?string>
     */
    private function cells(string $effectiveFrom): array
    {
        $cells = SalaryGrade::query()->whereDate('effective_from', $effectiveFrom)->orderBy('grade')->orderBy('step')->get();

        return [
            'legal_basis' => $cells->first()?->legal_basis,
            ...$cells->mapWithKeys(fn (SalaryGrade $cell): array => ['SG '.$cell->grade.'-'.$cell->step => (string) $cell->amount])->all(),
        ];
    }
}
