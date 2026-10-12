<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\EmployeeAllowanceRequest;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use Illuminate\Http\RedirectResponse;

/**
 * Assign an allowance to an employee, or remove the assignment.
 */
class EmployeeAllowanceController extends Controller
{
    public function store(EmployeeAllowanceRequest $request, Employee $employee): RedirectResponse
    {
        $allowance = $employee->allowances()->create($request->assignment());

        return redirect()->route('payroll.employees.show', $employee)
            ->with('status', __(':name assigned.', ['name' => $allowance->allowanceType->name]));
    }

    public function destroy(Employee $employee, EmployeeAllowance $allowance): RedirectResponse
    {
        $name = $allowance->allowanceType->name;
        $allowance->delete();

        return redirect()->route('payroll.employees.show', $employee)->with('status', __(':name removed.', ['name' => $name]));
    }
}
