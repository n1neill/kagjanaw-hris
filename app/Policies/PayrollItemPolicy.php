<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PayrollItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Payslips: HR officers see every payslip; employees see their own once the
 * payroll is released. Payslips a user may not see respond as "not found".
 */
class PayrollItemPolicy
{
    public function view(User $user, PayrollItem $payrollItem): Response
    {
        if ($user->can(Permission::ProcessPayroll)) {
            return Response::allow();
        }

        $isOwn = $user->employee !== null && $payrollItem->employee_id === $user->employee->id;

        return $isOwn && $user->can(Permission::ViewOwnPayslips) && $payrollItem->run->isReleased()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
