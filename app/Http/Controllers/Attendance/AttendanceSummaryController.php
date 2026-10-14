<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceSummaryRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Attendance\AttendanceReportService;
use App\Services\Reports\AttendanceSummaryReport;
use App\Services\Reports\ReportExporter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Monthly attendance totals: all departments for HR, their own department
 * for a department head.
 */
class AttendanceSummaryController extends Controller
{
    public function index(AttendanceSummaryRequest $request, AttendanceReportService $reports): View
    {
        $month = $request->month();
        [$employees, $department, $canManage] = $this->scope($request, $month);

        return view('attendance.summary', [
            'month' => $month,
            'rows' => $employees === null ? collect() : $reports->monthlySummary($employees, $month),
            'canManage' => $canManage,
            'department' => $department,
            'departments' => $canManage ? Department::query()->orderBy('name')->pluck('name', 'id') : collect(),
            'hasDepartment' => $employees !== null,
        ]);
    }

    public function export(AttendanceSummaryRequest $request, string $format, AttendanceReportService $reports, AttendanceSummaryReport $report, ReportExporter $exporter): Response
    {
        $month = $request->month();
        [$employees, $department] = $this->scope($request, $month);
        $rows = $employees === null ? collect() : $reports->monthlySummary($employees, $month);

        return $exporter->download($report->report($rows, $month, $department), $format);
    }

    /**
     * Employees in service at some point in the month, limited to the viewer's scope.
     *
     * @return array{0: ?Builder<Employee>, 1: ?Department, 2: bool}
     */
    private function scope(AttendanceSummaryRequest $request, CarbonImmutable $month): array
    {
        $user = $request->user();
        $canManage = $user->can(Permission::ManageAttendance);

        $departmentId = $canManage ? $request->validated('department') : $user->employee?->department_id;

        if (! $canManage && $departmentId === null) {
            return [null, null, false];
        }

        $employees = Employee::query()
            ->where('date_hired', '<=', $month->endOfMonth()->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('separation_date')->orWhere('separation_date', '>=', $month->startOfMonth()->toDateString()))
            ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId));

        return [$employees, $departmentId ? Department::find($departmentId) : null, $canManage];
    }
}
