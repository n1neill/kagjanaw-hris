<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\Payroll\Money;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeDeductionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring deduction (loan, cooperative share, dues), half of the
 * monthly amount per semi-monthly payroll, until its end date or until the
 * total has been deducted.
 */
#[Fillable(['employee_id', 'name', 'reference', 'monthly_amount', 'total_amount', 'starts_on', 'ends_on', 'priority', 'is_active', 'remarks'])]
class EmployeeDeduction extends Model
{
    /** @use HasFactory<EmployeeDeductionFactory> */
    use Auditable, HasFactory;

    /**
     * How the record is named in the audit log.
     */
    public function auditLabel(): string
    {
        return $this->name.' of employee '.$this->employee?->employee_number;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<PayrollItemDeduction, $this>
     */
    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollItemDeduction::class);
    }

    /**
     * Whether the deduction runs on any day from $from to $to.
     */
    public function overlaps(CarbonInterface $from, CarbonInterface $to): bool
    {
        return $this->starts_on->lessThanOrEqualTo($to)
            && ($this->ends_on === null || $this->ends_on->greaterThanOrEqualTo($from));
    }

    /**
     * Centavos already deducted in payroll runs other than $run (computed,
     * approved or released), counted against the total.
     */
    public function deductedOutside(PayrollRun $run): int
    {
        return Money::toCentavos((string) $this->payrollLines()
            ->whereHas('payrollItem', fn (Builder $items) => $items->where('payroll_run_id', '!=', $run->id))
            ->sum('amount'));
    }
}
