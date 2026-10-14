<?php

namespace App\Services\Reports;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The list of employees by office, with position, employment type, salary
 * grade and date hired. Government ID numbers are never included.
 */
class EmployeeListReport
{
    /**
     * @return Collection<int, Employee>
     */
    public function employees(?int $departmentId, ?int $employmentTypeId, ?EmploymentStatus $status): Collection
    {
        return Employee::query()
            ->with(['department', 'position', 'employmentType'])
            ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->when($employmentTypeId, fn (Builder $query) => $query->where('employment_type_id', $employmentTypeId))
            ->when($status, fn (Builder $query) => $query->where('employment_status', $status))
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->orderBy('departments.name')
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('employees.id')
            ->select('employees.*')
            ->get();
    }

    public function subtitle(?int $departmentId, ?int $employmentTypeId, ?EmploymentStatus $status): string
    {
        return collect([
            $departmentId ? Department::query()->whereKey($departmentId)->value('name') : __('All offices'),
            $employmentTypeId ? EmploymentType::query()->whereKey($employmentTypeId)->value('name') : null,
            $status?->label() ?? __('All statuses'),
            __('as of :date', ['date' => now()->format('F j, Y')]),
        ])->filter()->join(' · ');
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    public function report(Collection $employees, string $subtitle): Report
    {
        return new Report(
            title: __('Employee List'),
            subtitle: $subtitle,
            sections: [new ReportSection(
                columns: ['No.', 'Employee ID', 'Name', 'Position', 'Office', 'Employment type', 'Salary grade', 'Date hired', 'Status'],
                rows: $employees->values()->map(fn (Employee $employee, int $index): array => [
                    $index + 1,
                    $employee->employee_number,
                    $employee->sortable_name,
                    $employee->position->title,
                    $employee->department->name,
                    $employee->employmentType->name,
                    $employee->salary_grade === null ? '—' : $employee->salary_grade.'-'.$employee->salary_step,
                    $employee->date_hired->format('Y-m-d'),
                    $employee->employment_status->label(),
                ])->all(),
                numberColumns: [0],
            )],
            filename: 'employee-list-'.now()->format('Y-m-d'),
        );
    }
}
