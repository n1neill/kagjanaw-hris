<?php

namespace App\Services\Leave;

use App\Enums\LeaveCreditSource;

/**
 * Decides which credits pay for a leave application (CSC Omnibus Rules on Leave):
 *
 *  - Vacation and forced leave use vacation leave credits.
 *  - Sick leave uses sick leave credits, then vacation leave credits.
 *  - Days beyond the available credits are leave without pay.
 *  - Leave with its own yearly allotment, or granted by law per occasion,
 *    is paid in full and not charged to credits. (Exceeding an allotment is
 *    rejected when the application is filed.)
 */
final class LeaveChargeCalculator
{
    /**
     * @param  int  $days  The days applied for, in thousandths.
     * @param  int  $vacationAvailable  Vacation leave credits available, in thousandths.
     * @param  int  $sickAvailable  Sick leave credits available, in thousandths.
     */
    public function charge(LeaveCreditSource $source, int $days, int $vacationAvailable, int $sickAvailable): LeaveCharge
    {
        $vacationAvailable = max(0, $vacationAvailable);
        $sickAvailable = max(0, $sickAvailable);

        if ($source === LeaveCreditSource::VacationCredits) {
            $fromVacation = min($days, $vacationAvailable);

            return new LeaveCharge(fromVacation: $fromVacation, withoutPay: $days - $fromVacation);
        }

        if ($source === LeaveCreditSource::SickCredits) {
            $fromSick = min($days, $sickAvailable);
            $fromVacation = min($days - $fromSick, $vacationAvailable);

            return new LeaveCharge(
                fromVacation: $fromVacation,
                fromSick: $fromSick,
                withoutPay: $days - $fromSick - $fromVacation,
            );
        }

        return new LeaveCharge(notCharged: $days);
    }
}
