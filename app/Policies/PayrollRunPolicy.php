<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PayrollRun;
use App\Models\User;

/**
 * Approving, reopening and releasing a payroll run is the HR Officer's
 * supervision step: a user who was only granted payroll access may prepare
 * and compute runs, but not decide on them.
 */
class PayrollRunPolicy
{
    public function approve(User $user, PayrollRun $run): bool
    {
        return $user->can(Permission::ProcessPayroll) && ! $user->needsApprovalFor(Permission::ProcessPayroll);
    }
}
