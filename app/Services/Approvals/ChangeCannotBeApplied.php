<?php

namespace App\Services\Approvals;

use RuntimeException;

/**
 * A submitted change can no longer be applied, e.g. its record was deleted
 * while it waited for approval.
 */
class ChangeCannotBeApplied extends RuntimeException {}
