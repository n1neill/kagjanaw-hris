<?php

namespace App\Models;

use App\Enums\LeavePortion;
use Database\Factories\LeaveRequestDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One date covered by a leave application.
 */
#[Fillable(['leave_request_id', 'employee_id', 'date', 'portion', 'days', 'days_with_pay'])]
class LeaveRequestDay extends Model
{
    /** @use HasFactory<LeaveRequestDayFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'portion' => LeavePortion::class,
            'days' => 'decimal:3',
            'days_with_pay' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
