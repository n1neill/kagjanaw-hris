<?php

namespace App\Models;

use App\Enums\LeaveEndorsementRoute;
use App\Enums\LeavePortion;
use App\Enums\LeaveRequestStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An application for leave (CS Form No. 6). Status and workflow fields are
 * changed only by App\Services\Leave\LeaveRequestService.
 */
#[Fillable([
    'employee_id',
    'leave_type_id',
    'date_from',
    'date_to',
    'portion',
    'days',
    'details',
])]
#[Hidden(['attachment_path'])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use Auditable, HasFactory;

    /**
     * How the record is named in the audit log.
     */
    public function auditLabel(): string
    {
        return 'Leave application #'.$this->getKey().' of employee '.$this->employee?->employee_number;
    }

    public const ATTACHMENT_DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'portion' => LeavePortion::class,
            'days' => 'decimal:3',
            'days_with_pay' => 'decimal:3',
            'days_without_pay' => 'decimal:3',
            'status' => LeaveRequestStatus::class,
            'endorsement_route' => LeaveEndorsementRoute::class,
            'endorsed_at' => 'datetime',
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'attachment_size' => 'integer',
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
     * @return HasMany<LeaveRequestDay, $this>
     */
    public function leaveDays(): HasMany
    {
        return $this->hasMany(LeaveRequestDay::class)->orderBy('date');
    }

    /**
     * @return HasMany<LeaveLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LeaveLedgerEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function filer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function endorser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'endorsed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isFiledBy(User $user): bool
    {
        return $this->employee?->user_id !== null && $this->employee->user_id === $user->id;
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    /**
     * "Oct 5–7, 2026", "Oct 5, 2026 (morning)" and similar.
     */
    public function periodLabel(): string
    {
        $from = $this->date_from;
        $to = $this->date_to;

        if ($from->isSameDay($to)) {
            return $from->format('M j, Y').($this->portion === LeavePortion::FullDay ? '' : ' ('.mb_strtolower($this->portion->label()).')');
        }

        return match (true) {
            $from->isSameMonth($to) => $from->format('M j').'–'.$to->format('j, Y'),
            $from->isSameYear($to) => $from->format('M j').' – '.$to->format('M j, Y'),
            default => $from->format('M j, Y').' – '.$to->format('M j, Y'),
        };
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     */
    #[Scope]
    protected function holdingDates(Builder $query): void
    {
        $query->whereIn('status', LeaveRequestStatus::valuesHoldingDates());
    }
}
