<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * A payslip as a PDF: HR may open any; employees only their own, once the
 * payroll has been released.
 */
class PayslipController extends Controller
{
    public function __invoke(PayrollItem $payrollItem): Response
    {
        Gate::authorize('view', $payrollItem);

        $payrollItem->load(['run.period', 'employee', 'deductionLines']);

        $pdf = Pdf::loadView('payroll.payslip-pdf', ['item' => $payrollItem])
            ->setPaper('a5', 'portrait')
            ->setOption('isFontSubsettingEnabled', true);

        $period = $payrollItem->run->period;

        return $pdf->stream(sprintf('payslip-%s-%d-%02d-%d.pdf', $payrollItem->employee->employee_number, $period->year, $period->month, $period->half));
    }
}
