<?php

namespace App\Services\Attendance;

use InvalidArgumentException;

/**
 * A work schedule reduced to minutes after midnight, for DTR calculations.
 * The day has a morning session (amStart-amEnd) and an afternoon session
 * (pmStart-pmEnd), matching the four columns of CSC Form No. 48.
 */
final readonly class ScheduleRules
{
    public function __construct(
        public int $amStart,
        public int $amEnd,
        public int $pmStart,
        public int $pmEnd,
        public int $graceMinutes,
    ) {
        if (! ($amStart < $amEnd && $amEnd <= $pmStart && $pmStart < $pmEnd)) {
            throw new InvalidArgumentException('Schedule sessions must be in order: AM start < AM end <= PM start < PM end.');
        }
    }

    /**
     * Build from "HH:MM" or "HH:MM:SS" strings.
     */
    public static function fromTimes(string $amStart, string $amEnd, string $pmStart, string $pmEnd, int $graceMinutes): self
    {
        return new self(
            self::toMinutes($amStart),
            self::toMinutes($amEnd),
            self::toMinutes($pmStart),
            self::toMinutes($pmEnd),
            $graceMinutes,
        );
    }

    public static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    public static function toTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Punches before this minute belong to the morning session; the rest to the afternoon.
     */
    public function middayBoundary(): int
    {
        return intdiv($this->amEnd + $this->pmStart, 2);
    }

    public function amLength(): int
    {
        return $this->amEnd - $this->amStart;
    }

    public function pmLength(): int
    {
        return $this->pmEnd - $this->pmStart;
    }
}
