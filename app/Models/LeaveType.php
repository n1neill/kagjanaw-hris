<?php

namespace App\Models;

use App\Enums\LeaveCreditSource;
use App\Enums\Sex;
use App\Models\Concerns\Auditable;
use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of leave (CS Form No. 6, item 6.A) and the rules for filing it.
 *
 * Only Vacation Leave and Sick Leave have balances on the leave card. Other
 * types are either charged to them (Forced Leave), limited by a yearly
 * allotment (Special Privilege Leave), or granted per occasion by law
 * (Maternity Leave).
 */
#[Fillable([
    'code',
    'name',
    'description',
    'credit_source',
    'annual_days',
    'max_days_per_request',
    'counts_calendar_days',
    'allows_half_day',
    'advance_notice_days',
    'allows_past_dates',
    'eligible_sex',
    'attachment_required_over_days',
    'attachment_hint',
    'sort_order',
    'is_active',
])]
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use Auditable, HasFactory;

    public const VACATION = 'VL';

    public const SICK = 'SL';

    public const FORCED = 'FL';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_source' => LeaveCreditSource::class,
            'annual_days' => 'decimal:3',
            'max_days_per_request' => 'decimal:3',
            'counts_calendar_days' => 'boolean',
            'allows_half_day' => 'boolean',
            'advance_notice_days' => 'integer',
            'allows_past_dates' => 'boolean',
            'eligible_sex' => Sex::class,
            'attachment_required_over_days' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function vacation(): self
    {
        return static::query()->where('code', self::VACATION)->sole();
    }

    public static function sick(): self
    {
        return static::query()->where('code', self::SICK)->sole();
    }

    /**
     * Whether employees have a running balance of this type on the leave card.
     */
    public function hasLedger(): bool
    {
        return in_array($this->code, [self::VACATION, self::SICK], true);
    }

    /**
     * Whether an attachment is required for an application of this many days.
     */
    public function requiresAttachmentFor(int $thousandths): bool
    {
        return $this->attachment_required_over_days !== null
            && $thousandths > $this->attachment_required_over_days * 1000;
    }

    /**
     * @param  Builder<LeaveType>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<LeaveType>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
