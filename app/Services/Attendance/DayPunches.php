<?php

namespace App\Services\Attendance;

/**
 * The four DTR punches of one day, in minutes after midnight (null when missing).
 */
final readonly class DayPunches
{
    public function __construct(
        public ?int $amIn = null,
        public ?int $amOut = null,
        public ?int $pmIn = null,
        public ?int $pmOut = null,
    ) {}

    /**
     * Build from "HH:MM" strings (or nulls), e.g. a stored or corrected DTR row.
     */
    public static function fromTimes(?string $amIn, ?string $amOut, ?string $pmIn, ?string $pmOut): self
    {
        $toMinutes = fn (?string $time): ?int => filled($time) ? ScheduleRules::toMinutes($time) : null;

        return new self($toMinutes($amIn), $toMinutes($amOut), $toMinutes($pmIn), $toMinutes($pmOut));
    }

    public function isEmpty(): bool
    {
        return $this->amIn === null && $this->amOut === null && $this->pmIn === null && $this->pmOut === null;
    }

    /**
     * @return array{am_in: ?string, am_out: ?string, pm_in: ?string, pm_out: ?string}
     */
    public function toTimes(): array
    {
        $toTime = fn (?int $minutes): ?string => $minutes === null ? null : ScheduleRules::toTime($minutes);

        return [
            'am_in' => $toTime($this->amIn),
            'am_out' => $toTime($this->amOut),
            'pm_in' => $toTime($this->pmIn),
            'pm_out' => $toTime($this->pmOut),
        ];
    }
}
