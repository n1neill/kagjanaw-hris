<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveUtilizationRequest;
use App\Models\Department;
use App\Services\Leave\LeaveDays;
use App\Services\Leave\LeaveReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leave utilization report: approved leave taken in a period, by type and office.
 */
class LeaveUtilizationController extends Controller
{
    public function __construct(private LeaveReportService $reports) {}

    public function index(LeaveUtilizationRequest $request): View
    {
        [$from, $to] = $request->period();

        return view('leave.utilization', [
            ...$this->reports->utilization($from, $to, $request->departmentId()),
            'from' => $from,
            'to' => $to,
            'year' => $request->year(),
            'month' => $request->monthNumber(),
            'departmentId' => $request->departmentId(),
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function export(LeaveUtilizationRequest $request): StreamedResponse
    {
        [$from, $to] = $request->period();
        $report = $this->reports->utilization($from, $to, $request->departmentId());

        return response()->streamDownload(function () use ($report, $from, $to): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Leave utilization', $from->format('M j, Y').' to '.$to->format('M j, Y')]);
            fputcsv($out, []);
            fputcsv($out, ['Leave type', 'Employees', 'Days taken', 'With pay', 'Without pay']);

            foreach ($report['byType'] as $row) {
                fputcsv($out, [$row['type']->name, $row['employees'], LeaveDays::format($row['days']), LeaveDays::format($row['with_pay']), LeaveDays::format($row['without_pay'])]);
            }

            fputcsv($out, ['Total', $report['totals']['employees'], LeaveDays::format($report['totals']['days']), LeaveDays::format($report['totals']['with_pay']), LeaveDays::format($report['totals']['without_pay'])]);
            fputcsv($out, []);
            fputcsv($out, ['Office', 'Employees', 'Days taken', 'With pay', 'Without pay']);

            foreach ($report['byDepartment'] as $row) {
                fputcsv($out, [$row['department'], $row['employees'], LeaveDays::format($row['days']), LeaveDays::format($row['with_pay']), LeaveDays::format($row['without_pay'])]);
            }

            fclose($out);
        }, sprintf('leave-utilization-%s-to-%s.csv', $from->format('Y-m-d'), $to->format('Y-m-d')), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
