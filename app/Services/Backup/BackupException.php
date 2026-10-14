<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * A backup could not be made, read or restored. The message is shown to the
 * administrator.
 */
class BackupException extends RuntimeException {}
