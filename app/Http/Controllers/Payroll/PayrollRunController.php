<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\ChangeAction;
use App\Enums\PayBasis;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StorePayrollRunRequest;
use App\Models\PayrollRun;
use App\Services\Approvals\ChangeRequestService;
use App\Services\Payroll\PayrollRegister;
use App\Services\Payroll\PayrollRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Payroll runs: the list, starting a new one, reviewing one and deleting a
 * run that has not been approved.
 */
class PayrollRunController extends Controller
{
    use SendsChangesForApproval;

    public function __construct(private PayrollRunService $payroll) {}

    public function index(): View
    {
        $today = now();

        return view('payroll.runs.index', [
            'runs' => PayrollRun::query()
                ->with('period')
                ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.payroll_period_id')
                ->orderByDesc('payroll_periods.start_date')
                ->orderBy('payroll_runs.pay_basis')
                ->select('payroll_runs.*')
                ->get(),
            'defaultMonth' => $today->format('Y-m'),
            'defaultHalf' => $today->day <= 15 ? 1 : 2,
            'payBases' => PayBasis::payrollOptions(),
        ]);
    }

    public function store(StorePayrollRunRequest $request): RedirectResponse
    {
        $month = $request->month();
        $run = $this->payroll->create($month->year, $month->month, $request->half(), $request->payBasis(), $request->user());
        $this->payroll->requestComputation($run, $request->user());

        return redirect()->route('payroll.runs.show', $run)->with('status', __('Payroll created and queued for computation.'));
    }

    public function show(PayrollRun $payrollRun, PayrollRegister $register): View
    {
        $payrollRun->load(['period', 'computer', 'approver', 'releaser']);

        return view('payroll.runs.show', [
            'run' => $payrollRun,
            ...$register->data($payrollRun),
            'register' => $register,
            'hasBlockingWarnings' => $this->payroll->hasBlockingWarnings($payrollRun),
        ]);
    }

    public function destroy(Request $request, PayrollRun $payrollRun, ChangeRequestService $changes): RedirectResponse
    {
        $title = $payrollRun->load('period')->title();
        $outcome = $changes->perform($request->user(), ChangeAction::DeletePayrollRun, [], $payrollRun);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('payroll.runs.show', $payrollRun));
        }

        return redirect()->route('payroll.runs.index')->with('status', __(':payroll deleted.', ['payroll' => $title]));
    }
}
