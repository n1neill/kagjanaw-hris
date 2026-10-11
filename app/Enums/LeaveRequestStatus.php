<?php

namespace App\Enums;

/**
 * Where a leave application stands in the approval workflow:
 * filed (pending) → endorsed → approved, or rejected/cancelled along the way.
 */
enum LeaveRequestStatus: string
{
    case Pending = 'pending';
    case Endorsed = 'endorsed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending endorsement',
            self::Endorsed => 'Endorsed to HR',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Endorsed => 'brand',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Still waiting for a decision (and holding the dates and credits).
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Endorsed], true);
    }

    /**
     * Open or approved: the dates are taken.
     */
    public function holdsDates(): bool
    {
        return $this->isOpen() || $this === self::Approved;
    }

    /**
     * @return list<string>
     */
    public static function valuesHoldingDates(): array
    {
        return [self::Pending->value, self::Endorsed->value, self::Approved->value];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
