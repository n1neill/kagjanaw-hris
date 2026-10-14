<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveUtilizationRequest;
use App\Models\Department;
use App\Services\Leave\LeaveReportService;
use App\Services\Reports\LeaveUtilizationReport;
use App\Services\Reports\ReportExporter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leave utilization report: approved leave taken in a period, by type and
 * office. Department heads see their own office only.
 */
class LeaveUtilizationController extends Controller
{
    public function __construct(private LeaveReportService $reports) {}

    public function index(LeaveUtilizationRequest $request): View
    {
        [$from, $to] = $request->period();

        return view('leave.utilization', [
            ...$this->utilization($request),
            'from' => $from,
            'to' => $to,
            'year' => $request->year(),
            'month' => $request->monthNumber(),
            'departmentId' => $request->departmentId(),
            'seesAllDepartments' => $request->seesAllDepartments(),
            'hasDepartment' => $request->hasDepartmentScope(),
            'ownDepartment' => $request->seesAllDepartments() ? null : Department::find($request->departmentId()),
            'departments' => $request->seesAllDepartments() ? Department::query()->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function export(LeaveUtilizationRequest $request, string $format, LeaveUtilizationReport $report, ReportExporter $exporter): Response
    {
        [$from, $to] = $request->period();
        $department = $request->departmentId() === null ? null : Department::find($request->departmentId());

        return $exporter->download($report->report($this->utilization($request), $from, $to, $department), $format);
    }

    /**
     * @return array<string, mixed>
     */
    private function utilization(LeaveUtilizationRequest $request): array
    {
        [$from, $to] = $request->period();

        return $request->hasDepartmentScope()
            ? $this->reports->utilization($from, $to, $request->departmentId())
            : ['byType' => collect(), 'byDepartment' => collect(), 'totals' => ['employees' => 0, 'days' => 0, 'with_pay' => 0, 'without_pay' => 0]];
    }
}
