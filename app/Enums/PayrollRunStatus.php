<?php

namespace App\Enums;

/**
 * A payroll run moves draft → computing → computed → approved → released.
 * It may be recomputed until approved, reopened from approved, and is
 * locked for good once released.
 */
enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Computing = 'computing';
    case Computed = 'computed';
    case Approved = 'approved';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Computing => 'Computing…',
            self::Computed => 'Computed',
            self::Approved => 'Approved',
            self::Released => 'Released',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Computing => 'yellow',
            self::Computed => 'blue',
            self::Approved => 'green',
            self::Released => 'green',
        };
    }

    /**
     * Computing is included so a run whose queued job was lost (e.g. the
     * worker stopped) can be queued again.
     */
    public function canCompute(): bool
    {
        return in_array($this, [self::Draft, self::Computing, self::Computed], true);
    }

    public function canDelete(): bool
    {
        return in_array($this, [self::Draft, self::Computed], true);
    }
}
