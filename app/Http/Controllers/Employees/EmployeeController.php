<?php

namespace App\Http\Controllers\Employees;

use App\Enums\CivilStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Enums\Sex;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employees\Concerns\RendersEmployeeRecord;
use App\Http\Requests\Employees\EmployeeIndexRequest;
use App\Http\Requests\Employees\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Services\EmployeeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    use RendersEmployeeRecord;

    /**
     * HR officers see every employee; department heads see only their own department.
     */
    public function index(EmployeeIndexRequest $request): View
    {
        $user = $request->user();
        $canManage = $user->can(Permission::ManageEmployees);
        $departmentId = $canManage ? $request->validated('department') : $user->employee?->department_id;
        $status = $request->status();

        $employees = $canManage || $departmentId !== null
            ? Employee::query()
                ->with(['department', 'position', 'employmentType'])
                ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
                ->when($status, fn (Builder $query) => $query->where('employment_status', $status))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('id')
                ->get()
            : collect();

        return view('employees.index', [
            'employees' => $employees,
            'canManage' => $canManage,
            'departments' => $canManage ? Department::query()->orderBy('name')->pluck('name', 'id') : collect(),
            'selectedDepartment' => $departmentId,
            'ownDepartment' => $canManage ? null : $user->employee?->department,
            'selectedStatus' => $status?->value ?? 'all',
            'statuses' => ['all' => __('All statuses')] + EmploymentStatus::options(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Employee::class);

        return view('employees.form', [
            'employee' => new Employee([
                'employment_status' => EmploymentStatus::Active,
                'date_hired' => now(),
            ]),
            ...$this->formOptions(null),
        ]);
    }

    public function store(EmployeeRequest $request, EmployeeService $employees): RedirectResponse
    {
        ['employee' => $employee, 'temporaryPassword' => $temporaryPassword] = $employees->create($request->validated());

        return redirect()
            ->route('employees.show', $employee)
            ->with('status', __(':name was added and a login account was created.', ['name' => $employee->full_name]))
            ->with('temporaryPassword', $temporaryPassword);
    }

    public function show(Request $request, Employee $employee): View
    {
        Gate::authorize('view', $employee);

        return $this->renderEmployeeRecord($request->user(), $employee);
    }

    public function edit(Employee $employee): View
    {
        Gate::authorize('update', $employee);

        $employee->load('user');

        return view('employees.form', [
            'employee' => $employee,
            ...$this->formOptions($employee),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee, EmployeeService $employees): RedirectResponse
    {
        $employees->update($employee, $request->validated());

        return redirect()
            ->route('employees.show', $employee)
            ->with('status', __('The record of :name was updated.', ['name' => $employee->full_name]));
    }

    /**
     * Select options for the form. Inactive reference data stays selectable only
     * when the employee already uses it.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?Employee $employee): array
    {
        $activeOrCurrent = fn (string $column) => fn (Builder $query) => $query
            ->where('is_active', true)
            ->when($employee, fn (Builder $query) => $query->orWhere('id', $employee->{$column}));

        return [
            'departments' => Department::query()->where($activeOrCurrent('department_id'))->orderBy('name')->pluck('name', 'id'),
            'positions' => Position::query()->where($activeOrCurrent('position_id'))->orderBy('title')->get(['id', 'title', 'salary_grade']),
            'employmentTypes' => EmploymentType::query()->where($activeOrCurrent('employment_type_id'))->orderBy('name')->pluck('name', 'id'),
            'sexes' => Sex::options(),
            'civilStatuses' => CivilStatus::options(),
            'employmentStatuses' => EmploymentStatus::options(),
        ];
    }
}
