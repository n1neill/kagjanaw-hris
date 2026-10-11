<?php

namespace App\Enums;

/**
 * Steps of the leave workflow that notify someone.
 */
enum LeaveRequestEvent: string
{
    /**
     * Sent to the endorsers (or to HR when no endorsement is needed).
     */
    case Filed = 'filed';

    /**
     * Sent to the employee and to HR.
     */
    case Endorsed = 'endorsed';

    case Rejected = 'rejected';
    case Approved = 'approved';
    case Cancelled = 'cancelled';
}
