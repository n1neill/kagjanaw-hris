<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\AllowanceCoverage;
use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\AllowanceTypeRequest;
use App\Models\AllowanceType;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Allowances HR defines. Deactivated rather than deleted, so past payslips
 * keep their history.
 */
class AllowanceTypeController extends Controller
{
    use SendsChangesForApproval;

    public function index(): View
    {
        return view('payroll.allowances.index', [
            'types' => AllowanceType::query()->withCount('assignments')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new AllowanceType(['coverage' => AllowanceCoverage::Assigned, 'is_active' => true]));
    }

    public function store(AllowanceTypeRequest $request, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::CreateAllowanceType, $request->validated());

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('payroll.allowances.index'));
        }

        $type = $outcome->result;

        return redirect()->route('payroll.allowances.index')->with('status', __(':name added.', ['name' => $type->name]));
    }

    public function edit(AllowanceType $allowanceType): View
    {
        return $this->form($allowanceType);
    }

    public function update(AllowanceTypeRequest $request, AllowanceType $allowanceType, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::UpdateAllowanceType, $request->validated(), $allowanceType);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('payroll.allowances.index'));
        }

        return redirect()->route('payroll.allowances.index')->with('status', __(':name updated.', ['name' => $allowanceType->name]));
    }

    private function form(AllowanceType $type): View
    {
        return view('payroll.allowances.form', ['type' => $type, 'coverages' => AllowanceCoverage::options()]);
    }
}
