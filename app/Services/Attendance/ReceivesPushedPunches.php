<?php

namespace App\Services\Attendance;

/**
 * Marks a driver whose device sends its punches to the HRIS (through the
 * kiosk API) instead of being read by the scheduled synchronization.
 */
interface ReceivesPushedPunches {}
