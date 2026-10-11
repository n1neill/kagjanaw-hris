<?php

namespace App\Enums;

/**
 * Kinds of lines on an employee's leave card (vacation and sick leave ledger).
 */
enum LeaveLedgerEntryType: string
{
    case OpeningBalance = 'opening-balance';
    case Accrual = 'accrual';
    case Availment = 'availment';
    case Tardiness = 'tardiness';
    case Adjustment = 'adjustment';
    case Forfeiture = 'forfeiture';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'Balance brought forward',
            self::Accrual => 'Earned',
            self::Availment => 'Leave with pay',
            self::Tardiness => 'Tardiness and undertime',
            self::Adjustment => 'Adjustment',
            self::Forfeiture => 'Forfeited (forced leave not taken)',
            self::Reversal => 'Restored (leave cancelled)',
        };
    }
}
