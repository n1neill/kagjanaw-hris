<?php

namespace App\Services\Leave;

/**
 * How a leave application is paid for, in thousandths of a day.
 */
final readonly class LeaveCharge
{
    public function __construct(
        public int $fromVacation = 0,
        public int $fromSick = 0,
        public int $notCharged = 0,
        public int $withoutPay = 0,
    ) {}

    /**
     * Days with pay, whether charged to credits or granted by law.
     */
    public function withPay(): int
    {
        return $this->fromVacation + $this->fromSick + $this->notCharged;
    }

    public function total(): int
    {
        return $this->withPay() + $this->withoutPay;
    }
}
