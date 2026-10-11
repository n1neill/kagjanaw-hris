<?php

namespace App\Services\Attendance;

use App\Enums\LeavePortion;

/**
 * Which sessions of a day are covered by approved leave.
 */
enum LeaveCoverage
{
    case None;
    case Morning;
    case Afternoon;
    case FullDay;

    /**
     * @param  iterable<LeavePortion>  $portions  Approved leave portions on one date.
     */
    public static function fromPortions(iterable $portions): self
    {
        $morning = false;
        $afternoon = false;

        foreach ($portions as $portion) {
            $morning = $morning || $portion !== LeavePortion::Afternoon;
            $afternoon = $afternoon || $portion !== LeavePortion::Morning;
        }

        return match (true) {
            $morning && $afternoon => self::FullDay,
            $morning => self::Morning,
            $afternoon => self::Afternoon,
            default => self::None,
        };
    }

    public function coversMorning(): bool
    {
        return $this === self::Morning || $this === self::FullDay;
    }

    public function coversAfternoon(): bool
    {
        return $this === self::Afternoon || $this === self::FullDay;
    }
}
