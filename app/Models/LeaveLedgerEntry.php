<?php

namespace App\Models;

use App\Enums\LeaveLedgerEntryType;
use Database\Factories\LeaveLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the leave card. Entries are never edited or deleted; mistakes
 * are corrected with an adjustment entry.
 */
#[Fillable([
    'employee_id',
    'leave_type_id',
    'entry_type',
    'entry_date',
    'period',
    'days',
    'balance_after',
    'days_without_pay',
    'leave_request_id',
    'particulars',
    'remarks',
    'created_by',
])]
class LeaveLedgerEntry extends Model
{
    /** @use HasFactory<LeaveLedgerEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_type' => LeaveLedgerEntryType::class,
            'entry_date' => 'date',
            'period' => 'date',
            'days' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'days_without_pay' => 'decimal:3',
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
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
