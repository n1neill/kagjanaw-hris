<?php

namespace App\Models;

use Database\Factories\PayrollItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One employee's pay in a payroll run, written only by
 * App\Services\Payroll\PayrollRunService.
 *
 * @property list<array{label: string, amount: string}> $earnings
 * @property list<string>|null $notes
 */
class PayrollItem extends Model
{
    /** @use HasFactory<PayrollItemFactory> */
    use HasFactory;

    /**
     * Every column is computed; nothing comes from user input.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salary_grade' => 'integer',
            'salary_step' => 'integer',
            'monthly_rate' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'worked_minutes' => 'integer',
            'basic_pay' => 'decimal:2',
            'allowances' => 'decimal:2',
            'attendance_deductions' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'earnings' => 'array',
            'gsis' => 'decimal:2',
            'philhealth' => 'decimal:2',
            'pagibig' => 'decimal:2',
            'withholding_tax' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'gsis_employer' => 'decimal:2',
            'philhealth_employer' => 'decimal:2',
            'pagibig_employer' => 'decimal:2',
            'notes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
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
    public function deductionLines(): HasMany
    {
        return $this->hasMany(PayrollItemDeduction::class);
    }

    /**
     * @return HasMany<PayrollAttendanceDeduction, $this>
     */
    public function attendanceLines(): HasMany
    {
        return $this->hasMany(PayrollAttendanceDeduction::class)->orderBy('month');
    }
}
