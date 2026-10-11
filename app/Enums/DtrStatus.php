<?php

namespace App\Enums;

/**
 * The outcome of one employee's day on the Daily Time Record.
 */
enum DtrStatus: string
{
    case Pending = 'pending';
    case Present = 'present';
    case HalfDay = 'half-day';
    case Absent = 'absent';
    case Incomplete = 'incomplete';
    case OnLeave = 'on-leave';
    case Holiday = 'holiday';
    case RestDay = 'rest-day';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In progress',
            self::Present => 'Present',
            self::HalfDay => 'Half day',
            self::Absent => 'Absent',
            self::Incomplete => 'Missing punch',
            self::OnLeave => 'On leave',
            self::Holiday => 'Holiday',
            self::RestDay => 'Rest day',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Present => 'green',
            self::Pending => 'gray',
            self::HalfDay, self::Incomplete => 'yellow',
            self::Absent => 'red',
            self::Holiday, self::OnLeave => 'brand',
            self::RestDay => 'gray',
        };
    }

    /**
     * Whether the day was a working day the employee was expected to report.
     */
    public function isWorkingDay(): bool
    {
        return ! in_array($this, [self::Holiday, self::RestDay], true);
    }
}
