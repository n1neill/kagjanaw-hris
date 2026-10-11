<?php

namespace App\Services\Attendance;

/**
 * Whether the employee is expected to report on a given date.
 */
enum DayType
{
    case Working;
    case Holiday;
    case RestDay;
}
