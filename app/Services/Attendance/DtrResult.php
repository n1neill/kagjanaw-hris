<?php

namespace App\Services\Attendance;

use App\Enums\DtrStatus;

/**
 * The computed totals for one DTR day, in minutes.
 */
final readonly class DtrResult
{
    public function __construct(
        public DtrStatus $status,
        public int $lateMinutes = 0,
        public int $undertimeMinutes = 0,
        public int $absentMinutes = 0,
        public int $workedMinutes = 0,
    ) {}
}
