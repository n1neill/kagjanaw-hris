<?php

namespace App\Models;

use App\Enums\DtrStatus;
use Database\Factories\DailyTimeRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One employee's attendance for one day (a line of CSC Form No. 48).
 */
#[Fillable([
    'employee_id',
    'work_date',
    'am_in',
    'am_out',
    'pm_in',
    'pm_out',
    'late_minutes',
    'undertime_minutes',
    'absent_minutes',
    'worked_minutes',
    'status',
    'is_adjusted',
])]
class DailyTimeRecord extends Model
{
    /** @use HasFactory<DailyTimeRecordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'late_minutes' => 'integer',
            'undertime_minutes' => 'integer',
            'absent_minutes' => 'integer',
            'worked_minutes' => 'integer',
            'status' => DtrStatus::class,
            'is_adjusted' => 'boolean',
            'validated_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * @return HasMany<DailyTimeRecordAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(DailyTimeRecordAdjustment::class);
    }

    /**
     * A stored punch as printed on the DTR, e.g. "8:01" (12-hour clock, no AM/PM).
     */
    public static function displayTime(?string $time): string
    {
        if (blank($time)) {
            return '';
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return sprintf('%d:%02d', $hours % 12 === 0 ? 12 : $hours % 12, $minutes);
    }

    /**
     * Late plus undertime, the total CSC Form 48 reports as "undertime".
     */
    public function tardinessMinutes(): int
    {
        return $this->late_minutes + $this->undertime_minutes;
    }
}
