<?php

namespace App\Http\Controllers\Reports;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\EmployeeListRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Services\Reports\EmployeeListReport;
use App\Services\Reports\ReportExporter;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The employee list report: every office for HR, their own office for
 * department heads.
 */
class EmployeeListController extends Controller
{
    public function __construct(private EmployeeListReport $report) {}

    public function index(EmployeeListRequest $request): View
    {
        return view('reports.employees', [
            'employees' => $this->employees($request),
            'subtitle' => $this->subtitle($request),
            'seesAllDepartments' => $request->seesAllDepartments(),
            'hasDepartment' => $request->hasDepartmentScope(),
            'departmentId' => $request->departmentId(),
            'employmentTypeId' => $request->employmentTypeId(),
            'status' => $request->status()?->value ?? 'all',
            'departments' => $request->seesAllDepartments() ? Department::query()->orderBy('name')->pluck('name', 'id') : collect(),
            'employmentTypes' => EmploymentType::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => ['all' => __('All statuses')] + EmploymentStatus::options(),
        ]);
    }

    public function export(EmployeeListRequest $request, string $format, ReportExporter $exporter): Response
    {
        return $exporter->download($this->report->report($this->employees($request), $this->subtitle($request)), $format);
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employees(EmployeeListRequest $request): Collection
    {
        return $request->hasDepartmentScope()
            ? $this->report->employees($request->departmentId(), $request->employmentTypeId(), $request->status())
            : collect();
    }

    private function subtitle(EmployeeListRequest $request): string
    {
        return $this->report->subtitle($request->departmentId(), $request->employmentTypeId(), $request->status());
    }
}
