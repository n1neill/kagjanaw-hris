<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollRegister;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * The payroll register of a run as Excel (.xlsx), CSV or PDF.
 */
class PayrollRegisterController extends Controller
{
    public function __invoke(PayrollRun $payrollRun, string $format, PayrollRegister $register): Response
    {
        $payrollRun->load('period');
        $filename = sprintf('payroll-register-%s-%d-%02d-%d', $payrollRun->pay_basis->value, $payrollRun->period->year, $payrollRun->period->month, $payrollRun->period->half);

        return match ($format) {
            'xlsx' => $register->xlsx($payrollRun, $filename.'.xlsx'),
            'csv' => $register->csv($payrollRun, $filename.'.csv'),
            'pdf' => Pdf::loadView('payroll.register-pdf', ['run' => $payrollRun, ...$register->data($payrollRun)])
                ->setPaper('legal', 'landscape')
                ->setOption('isFontSubsettingEnabled', true)
                ->stream($filename.'.pdf'),
        };
    }
}
