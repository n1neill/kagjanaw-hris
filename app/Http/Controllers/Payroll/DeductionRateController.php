<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\ContributionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\DeductionRateRequest;
use App\Models\DeductionRate;
use App\Models\TaxBracket;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Contribution rates and the withholding tax table, each with effectivity
 * dates, so new rates can be entered ahead of the date they take effect.
 */
class DeductionRateController extends Controller
{
    public function index(): View
    {
        return view('payroll.rates.index', [
            'rates' => DeductionRate::query()->orderBy('type')->orderByDesc('effective_from')->get()->groupBy(fn (DeductionRate $rate): string => $rate->type->value),
            'types' => ContributionType::cases(),
            'taxTables' => TaxBracket::query()->orderByDesc('effective_from')->orderBy('over')->get()
                ->groupBy(fn (TaxBracket $bracket): string => $bracket->effective_from->toDateString()),
        ]);
    }

    public function create(): View
    {
        return $this->form(new DeductionRate(['type' => ContributionType::Gsis]));
    }

    public function store(DeductionRateRequest $request): RedirectResponse
    {
        $rate = DeductionRate::create($request->validated());

        return redirect()->route('payroll.rates.index')->with('status', __(':type rate added.', ['type' => $rate->type->label()]));
    }

    public function edit(DeductionRate $deductionRate): View
    {
        return $this->form($deductionRate);
    }

    public function update(DeductionRateRequest $request, DeductionRate $deductionRate): RedirectResponse
    {
        $deductionRate->update($request->validated());

        return redirect()->route('payroll.rates.index')->with('status', __(':type rate updated.', ['type' => $deductionRate->type->label()]));
    }

    private function form(DeductionRate $rate): View
    {
        return view('payroll.rates.contribution-form', ['rate' => $rate, 'types' => ContributionType::options()]);
    }
}
