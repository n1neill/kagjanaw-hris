<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\EmployeeDeductionRequest;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Loans and other recurring deductions of an employee. Deductions are
 * deactivated rather than deleted, so payroll history stays complete.
 */
class EmployeeDeductionController extends Controller
{
    public function store(EmployeeDeductionRequest $request, Employee $employee): RedirectResponse
    {
        $deduction = $employee->payrollDeductions()->make($request->validated());
        $deduction->forceFill(['created_by' => $request->user()->id])->save();

        return redirect()->route('payroll.employees.show', $employee)->with('status', __(':name added.', ['name' => $deduction->name]));
    }

    public function edit(Employee $employee, EmployeeDeduction $payrollDeduction): View
    {
        return view('payroll.employees.deduction-form', ['employee' => $employee, 'deduction' => $payrollDeduction]);
    }

    public function update(EmployeeDeductionRequest $request, Employee $employee, EmployeeDeduction $payrollDeduction): RedirectResponse
    {
        $payrollDeduction->update($request->validated());

        return redirect()->route('payroll.employees.show', $employee)->with('status', __(':name updated.', ['name' => $payrollDeduction->name]));
    }
}
