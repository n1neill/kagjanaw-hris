<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\AllowanceCoverage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\AllowanceTypeRequest;
use App\Models\AllowanceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Allowances HR defines. Deactivated rather than deleted, so past payslips
 * keep their history.
 */
class AllowanceTypeController extends Controller
{
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

    public function store(AllowanceTypeRequest $request): RedirectResponse
    {
        $type = AllowanceType::create($request->validated());

        return redirect()->route('payroll.allowances.index')->with('status', __(':name added.', ['name' => $type->name]));
    }

    public function edit(AllowanceType $allowanceType): View
    {
        return $this->form($allowanceType);
    }

    public function update(AllowanceTypeRequest $request, AllowanceType $allowanceType): RedirectResponse
    {
        $allowanceType->update($request->validated());

        return redirect()->route('payroll.allowances.index')->with('status', __(':name updated.', ['name' => $allowanceType->name]));
    }

    private function form(AllowanceType $type): View
    {
        return view('payroll.allowances.form', ['type' => $type, 'coverages' => AllowanceCoverage::options()]);
    }
}
