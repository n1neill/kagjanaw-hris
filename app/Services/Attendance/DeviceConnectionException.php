<?php

namespace App\Services\Attendance;

use RuntimeException;

/**
 * The device could not be reached or did not answer as expected.
 * The message is shown to administrators, so it must not contain secrets.
 */
class DeviceConnectionException extends RuntimeException {}
