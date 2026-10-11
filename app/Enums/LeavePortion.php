<?php

namespace App\Enums;

/**
 * The part of a day covered by leave.
 */
enum LeavePortion: string
{
    case FullDay = 'full';
    case Morning = 'am';
    case Afternoon = 'pm';

    public function label(): string
    {
        return match ($this) {
            self::FullDay => 'Whole day',
            self::Morning => 'Morning only',
            self::Afternoon => 'Afternoon only',
        };
    }

    /**
     * Days of leave, in thousandths (1 day = 1000).
     */
    public function thousandths(): int
    {
        return $this === self::FullDay ? 1000 : 500;
    }
}
