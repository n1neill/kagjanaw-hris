<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\NewEffectivityRequest;
use App\Http\Requests\Payroll\TaxTableRequest;
use App\Models\TaxBracket;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The monthly withholding tax table by effectivity date. A new table starts
 * as a copy of the one in force.
 */
class TaxTableController extends Controller
{
    use SendsChangesForApproval;

    public function store(NewEffectivityRequest $request, ChangeRequestService $changes): RedirectResponse
    {
        $effectiveFrom = $request->effectiveFrom()->toDateString();

        if (TaxBracket::effectivityDates()->contains($effectiveFrom)) {
            throw ValidationException::withMessages(['effective_from' => __('A tax table effective on that date already exists.')]);
        }

        $outcome = $changes->perform($request->user(), ChangeAction::CreateTaxTable, ['effective_from' => $effectiveFrom]);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('payroll.rates.index'));
        }

        return redirect()->route('payroll.rates.tax.edit', $effectiveFrom)
            ->with('status', __('New tax table created as a copy of the table in force. Enter the new brackets.'));
    }

    public function edit(string $effectiveFrom): View
    {
        abort_unless(TaxBracket::effectivityDates()->contains($effectiveFrom), 404);

        $brackets = TaxBracket::query()->whereDate('effective_from', $effectiveFrom)->orderBy('over')->get();

        return view('payroll.rates.tax-form', [
            'effectiveFrom' => $effectiveFrom,
            'brackets' => $brackets,
            'legalBasis' => $brackets->first()?->legal_basis,
        ]);
    }

    public function update(TaxTableRequest $request, string $effectiveFrom, ChangeRequestService $changes): RedirectResponse
    {
        abort_unless(TaxBracket::effectivityDates()->contains($effectiveFrom), 404);

        $outcome = $changes->perform($request->user(), ChangeAction::UpdateTaxTable, [
            'effective_from' => $effectiveFrom,
            'brackets' => $request->validated('brackets'),
            'legal_basis' => $request->validated('legal_basis'),
        ]);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('payroll.rates.index'));
        }

        return redirect()->route('payroll.rates.index')->with('status', __('Tax table saved.'));
    }

    public function destroy(Request $request, string $effectiveFrom, ChangeRequestService $changes): RedirectResponse
    {
        $dates = TaxBracket::effectivityDates();

        abort_unless($dates->contains($effectiveFrom), 404);

        if ($dates->count() === 1) {
            return redirect()->route('payroll.rates.index')
                ->withErrors(['effective_from' => __('The only tax table cannot be removed. Add a replacement first.')]);
        }

        $outcome = $changes->perform($request->user(), ChangeAction::DeleteTaxTable, ['effective_from' => $effectiveFrom]);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('payroll.rates.index'));
        }

        return redirect()->route('payroll.rates.index')->with('status', __('Tax table removed.'));
    }
}
