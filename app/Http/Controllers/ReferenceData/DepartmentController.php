<?php

namespace App\Http\Controllers\ReferenceData;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReferenceData\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Departments are deactivated rather than deleted so historical records keep their office.
 */
class DepartmentController extends Controller
{
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

    public function store(DepartmentRequest $request): RedirectResponse
    {
        $department = Department::create($request->validated());

        return redirect()
            ->route('reference-data.departments.index')
            ->with('status', __(':name added.', ['name' => $department->name]));
    }

    public function edit(Department $department): View
    {
        return view('reference-data.departments.form', ['department' => $department]);
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return redirect()
            ->route('reference-data.departments.index')
            ->with('status', __(':name updated.', ['name' => $department->name]));
    }
}
