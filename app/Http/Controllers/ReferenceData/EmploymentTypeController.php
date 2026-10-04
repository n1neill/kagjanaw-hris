<?php

namespace App\Http\Controllers\ReferenceData;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReferenceData\EmploymentTypeRequest;
use App\Models\EmploymentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Employment types are deactivated rather than deleted so historical records keep their type.
 */
class EmploymentTypeController extends Controller
{
    public function index(): View
    {
        return view('reference-data.employment-types.index', [
            'employmentTypes' => EmploymentType::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('reference-data.employment-types.form', ['employmentType' => new EmploymentType(['is_active' => true])]);
    }

    public function store(EmploymentTypeRequest $request): RedirectResponse
    {
        $employmentType = EmploymentType::create($request->validated());

        return redirect()
            ->route('reference-data.employment-types.index')
            ->with('status', __(':name added.', ['name' => $employmentType->name]));
    }

    public function edit(EmploymentType $employmentType): View
    {
        return view('reference-data.employment-types.form', ['employmentType' => $employmentType]);
    }

    public function update(EmploymentTypeRequest $request, EmploymentType $employmentType): RedirectResponse
    {
        $employmentType->update($request->validated());

        return redirect()
            ->route('reference-data.employment-types.index')
            ->with('status', __(':name updated.', ['name' => $employmentType->name]));
    }
}
