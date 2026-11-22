<?php

namespace App\Http\Controllers\ReferenceData;

use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReferenceData\DepartmentRequest;
use App\Models\Department;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Departments are deactivated rather than deleted so historical records keep their office.
 */
class DepartmentController extends Controller
{
    use SendsChangesForApproval;

    public function index(): View
    {
        return view('reference-data.departments.index', [
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('reference-data.departments.form', ['department' => new Department(['is_active' => true])]);
    }

    public function store(DepartmentRequest $request, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::CreateDepartment, $request->validated());

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('reference-data.departments.index'));
        }

        $department = $outcome->result;

        return redirect()
            ->route('reference-data.departments.index')
            ->with('status', __(':name added.', ['name' => $department->name]));
    }

    public function edit(Department $department): View
    {
        return view('reference-data.departments.form', ['department' => $department]);
    }

    public function update(DepartmentRequest $request, Department $department, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::UpdateDepartment, $request->validated(), $department);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('reference-data.departments.index'));
        }

        return redirect()
            ->route('reference-data.departments.index')
            ->with('status', __(':name updated.', ['name' => $department->name]));
    }
}
