<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\EmployeeAllowanceRequest;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Assign an allowance to an employee, or remove the assignment.
 */
class EmployeeAllowanceController extends Controller
{
    use SendsChangesForApproval;

    public function store(EmployeeAllowanceRequest $request, Employee $employee, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::AssignAllowance, $request->assignment(), $employee);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('payroll.employees.show', $employee));
        }

        $allowance = $outcome->result;

        return redirect()->route('payroll.employees.show', $employee)
            ->with('status', __(':name assigned.', ['name' => $allowance->allowanceType->name]));
    }

    public function destroy(Request $request, Employee $employee, EmployeeAllowance $allowance, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::RemoveAllowance, [], $allowance);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('payroll.employees.show', $employee));
        }

        $name = $outcome->result;

        return redirect()->route('payroll.employees.show', $employee)->with('status', __(':name removed.', ['name' => $name]));
    }
}
