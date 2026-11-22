<?php

namespace App\Http\Controllers\ReferenceData;

use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReferenceData\EmploymentTypeRequest;
use App\Models\EmploymentType;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Employment types are deactivated rather than deleted so historical records keep their type.
 */
class EmploymentTypeController extends Controller
{
    use SendsChangesForApproval;

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

    public function store(EmploymentTypeRequest $request, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::CreateEmploymentType, $request->validated());

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('reference-data.employment-types.index'));
        }

        $employmentType = $outcome->result;

        return redirect()
            ->route('reference-data.employment-types.index')
            ->with('status', __(':name added.', ['name' => $employmentType->name]));
    }

    public function edit(EmploymentType $employmentType): View
    {
        return view('reference-data.employment-types.form', ['employmentType' => $employmentType]);
    }

    public function update(EmploymentTypeRequest $request, EmploymentType $employmentType, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::UpdateEmploymentType, $request->validated(), $employmentType);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('reference-data.employment-types.index'));
        }

        return redirect()
            ->route('reference-data.employment-types.index')
            ->with('status', __(':name updated.', ['name' => $employmentType->name]));
    }
}
