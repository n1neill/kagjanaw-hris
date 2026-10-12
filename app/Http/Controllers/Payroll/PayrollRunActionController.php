<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Compute, approve, reopen or release a payroll run.
 */
class PayrollRunActionController extends Controller
{
    public function __construct(private PayrollRunService $payroll) {}

    public function compute(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->payroll->requestComputation($payrollRun, $request->user());

        return $this->backTo($payrollRun, __('The payroll was queued for computation.'));
    }

    public function approve(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->payroll->approve($payrollRun, $request->user());

        return $this->backTo($payrollRun, __('The payroll was approved. Release it to make the payslips available.'));
    }

    public function reopen(PayrollRun $payrollRun): RedirectResponse
    {
        $this->payroll->reopen($payrollRun);

        return $this->backTo($payrollRun, __('The payroll was reopened for corrections.'));
    }

    public function release(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $run = $this->payroll->release($payrollRun, $request->user());

        return $this->backTo($run, trans_choice('{0} The payroll was released.|{1} The payroll was released; :count employee was notified.|[2,*] The payroll was released; :count employees were notified.', $run->employee_count, [
            'count' => $run->employee_count,
        ]));
    }

    private function backTo(PayrollRun $run, string $message): RedirectResponse
    {
        return redirect()->route('payroll.runs.show', $run)->with('status', $message);
    }
}
