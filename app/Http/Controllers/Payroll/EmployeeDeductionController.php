<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\EmployeeDeductionRequest;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Loans and other recurring deductions of an employee. Deductions are
 * deactivated rather than deleted, so payroll history stays complete.
 */
class EmployeeDeductionController extends Controller
{
    use SendsChangesForApproval;

    public function store(EmployeeDeductionRequest $request, Employee $employee, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::AddDeduction, $request->validated(), $employee);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('payroll.employees.show', $employee));
        }

        $deduction = $outcome->result;

        return redirect()->route('payroll.employees.show', $employee)->with('status', __(':name added.', ['name' => $deduction->name]));
    }

    public function edit(Employee $employee, EmployeeDeduction $payrollDeduction): View
    {
        return view('payroll.employees.deduction-form', ['employee' => $employee, 'deduction' => $payrollDeduction]);
    }

    public function update(EmployeeDeductionRequest $request, Employee $employee, EmployeeDeduction $payrollDeduction, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::UpdateDeduction, $request->validated(), $payrollDeduction);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('payroll.employees.show', $employee));
        }

        return redirect()->route('payroll.employees.show', $employee)->with('status', __(':name updated.', ['name' => $payrollDeduction->name]));
    }
}
