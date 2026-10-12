<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The absences and tardiness without pay of one attendance month, as
 * deducted in a payroll item (or, after a correction, the difference).
 */
#[Fillable(['payroll_item_id', 'employee_id', 'month', 'days_without_pay', 'absent_minutes', 'salary_amount', 'allowance_amount'])]
class PayrollAttendanceDeduction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'immutable_date',
            'days_without_pay' => 'decimal:3',
            'absent_minutes' => 'integer',
            'salary_amount' => 'decimal:2',
            'allowance_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PayrollItem, $this>
     */
    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }
}
