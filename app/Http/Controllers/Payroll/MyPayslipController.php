<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\PayrollRunStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in employee's released payslips, newest first.
 */
class MyPayslipController extends Controller
{
    public function __invoke(Request $request): View
    {
        $employee = $request->user()->employee;

        $payslips = $employee === null ? collect() : $employee->payrollItems()
            ->whereHas('run', fn ($runs) => $runs->where('status', PayrollRunStatus::Released))
            ->with('run.period')
            ->get()
            ->sortByDesc(fn ($item): string => $item->run->period->start_date->toDateString().'-'.$item->run->pay_basis->value)
            ->values();

        return view('payroll.my-payslips', ['employee' => $employee, 'payslips' => $payslips]);
    }
}
