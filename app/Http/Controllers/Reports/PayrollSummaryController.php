<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PayBasis;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\PayrollSummaryRequest;
use App\Services\Reports\PayrollSummaryReport;
use App\Services\Reports\ReportExporter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Totals of the released payrolls in a year or month (HR only).
 */
class PayrollSummaryController extends Controller
{
    public function __construct(private PayrollSummaryReport $report) {}

    public function index(PayrollSummaryRequest $request): View
    {
        return view('reports.payroll', [
            ...$this->report->data($request->year(), $request->monthNumber(), $request->payBasis()),
            'subtitle' => $this->report->subtitle($request->year(), $request->monthNumber(), $request->payBasis()),
            'year' => $request->year(),
            'month' => $request->monthNumber(),
            'payBasis' => $request->payBasis()?->value,
            'payBases' => PayBasis::payrollOptions(),
        ]);
    }

    public function export(PayrollSummaryRequest $request, string $format, ReportExporter $exporter): Response
    {
        $year = $request->year();
        $month = $request->monthNumber();
        $payBasis = $request->payBasis();

        $report = $this->report->report(
            $this->report->data($year, $month, $payBasis),
            $this->report->subtitle($year, $month, $payBasis),
            'payroll-summary-'.$year.($month === null ? '' : sprintf('-%02d', $month)).($payBasis === null ? '' : '-'.$payBasis->value),
        );

        return $exporter->download($report, $format);
    }
}
