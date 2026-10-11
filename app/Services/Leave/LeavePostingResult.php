<?php

namespace App\Services\Leave;

/**
 * The outcome of posting one employee's leave credits for one month.
 * Amounts are thousandths of a day.
 */
final readonly class LeavePostingResult
{
    public const POSTED = 'posted';

    public const UP_TO_DATE = 'up-to-date';

    public const SKIPPED = 'skipped';

    public function __construct(
        public string $status,
        public string $message,
        public int $earned = 0,
        public int $tardiness = 0,
        public int $tardinessWithoutPay = 0,
    ) {}

    public static function skipped(string $reason): self
    {
        return new self(self::SKIPPED, $reason);
    }

    public function wasPosted(): bool
    {
        return $this->status === self::POSTED;
    }
}
