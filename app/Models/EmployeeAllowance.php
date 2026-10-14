<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeAllowanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An allowance assigned to one employee, optionally at its own amount and
 * for a limited time.
 */
#[Fillable(['employee_id', 'allowance_type_id', 'monthly_amount', 'starts_on', 'ends_on'])]
class EmployeeAllowance extends Model
{
    /** @use HasFactory<EmployeeAllowanceFactory> */
    use Auditable, HasFactory;

    /**
     * How the record is named in the audit log.
     */
    public function auditLabel(): string
    {
        return $this->allowanceType?->name.' of employee '.$this->employee?->employee_number;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
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
     * @return BelongsTo<AllowanceType, $this>
     */
    public function allowanceType(): BelongsTo
    {
        return $this->belongsTo(AllowanceType::class);
    }

    /**
     * Whether the assignment covers any day from $from to $to.
     */
    public function overlaps(CarbonInterface $from, CarbonInterface $to): bool
    {
        return ($this->starts_on === null || $this->starts_on->lessThanOrEqualTo($to))
            && ($this->ends_on === null || $this->ends_on->greaterThanOrEqualTo($from));
    }
}
