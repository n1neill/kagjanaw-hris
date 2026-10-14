<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\DailyTimeRecordAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An audit-trail entry for a manual DTR correction.
 * "changes" maps each corrected field to its before/after values.
 */
#[Fillable(['daily_time_record_id', 'user_id', 'changes', 'reason'])]
class DailyTimeRecordAdjustment extends Model
{
    /** @use HasFactory<DailyTimeRecordAdjustmentFactory> */
    use Auditable, HasFactory;

    /**
     * How the record is named in the audit log.
     */
    public function auditLabel(): string
    {
        return 'DTR correction of employee '.$this->dailyTimeRecord?->employee?->employee_number.' for '.$this->dailyTimeRecord?->work_date?->toDateString();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DailyTimeRecord, $this>
     */
    public function dailyTimeRecord(): BelongsTo
    {
        return $this->belongsTo(DailyTimeRecord::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
