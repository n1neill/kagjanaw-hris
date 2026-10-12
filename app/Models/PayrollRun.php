<?php

namespace App\Models;

use App\Enums\PayBasis;
use App\Enums\PayrollRunStatus;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The payroll of one pay period for one pay basis. Status and totals are
 * changed only by App\Services\Payroll\PayrollRunService.
 */
#[Fillable(['payroll_period_id', 'pay_basis'])]
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pay_basis' => PayBasis::class,
            'status' => PayrollRunStatus::class,
            'employee_count' => 'integer',
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'warnings' => 'array',
            'computed_at' => 'datetime',
            'approved_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * @return HasMany<PayrollItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function computer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isReleased(): bool
    {
        return $this->status === PayrollRunStatus::Released;
    }

    /**
     * "Regular payroll, October 1–15, 2026".
     */
    public function title(): string
    {
        return $this->pay_basis->payrollLabel().', '.$this->period->label();
    }
}
