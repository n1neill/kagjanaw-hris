<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\NewEffectivityRequest;
use App\Http\Requests\Payroll\TaxTableRequest;
use App\Models\TaxBracket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The monthly withholding tax table by effectivity date. A new table starts
 * as a copy of the one in force.
 */
class TaxTableController extends Controller
{
    public function store(NewEffectivityRequest $request): RedirectResponse
    {
        $effectiveFrom = $request->effectiveFrom()->toDateString();

        if ($this->dates()->contains($effectiveFrom)) {
            throw ValidationException::withMessages(['effective_from' => __('A tax table effective on that date already exists.')]);
        }

        $source = TaxBracket::query()->whereDate('effective_from', '<=', $effectiveFrom)->max('effective_from')
            ?? TaxBracket::query()->max('effective_from');

        DB::transaction(function () use ($source, $effectiveFrom): void {
            TaxBracket::query()
                ->when($source, fn ($query) => $query->whereDate('effective_from', substr((string) $source, 0, 10)))
                ->get()
                ->each(fn (TaxBracket $bracket) => TaxBracket::create([
                    'effective_from' => $effectiveFrom,
                    'over' => $bracket->over,
                    'base_tax' => $bracket->base_tax,
                    'rate' => $bracket->rate,
                ]));
        });

        return redirect()->route('payroll.rates.tax.edit', $effectiveFrom)
            ->with('status', __('New tax table created as a copy of the table in force. Enter the new brackets.'));
    }

    public function edit(string $effectiveFrom): View
    {
        abort_unless($this->dates()->contains($effectiveFrom), 404);

        $brackets = TaxBracket::query()->whereDate('effective_from', $effectiveFrom)->orderBy('over')->get();

        return view('payroll.rates.tax-form', [
            'effectiveFrom' => $effectiveFrom,
            'brackets' => $brackets,
            'legalBasis' => $brackets->first()?->legal_basis,
        ]);
    }

    public function update(TaxTableRequest $request, string $effectiveFrom): RedirectResponse
    {
        abort_unless($this->dates()->contains($effectiveFrom), 404);

        DB::transaction(function () use ($request, $effectiveFrom): void {
            TaxBracket::query()->whereDate('effective_from', $effectiveFrom)->delete();

            foreach ($request->validated('brackets') as $bracket) {
                TaxBracket::create([...$bracket, 'effective_from' => $effectiveFrom, 'legal_basis' => $request->validated('legal_basis')]);
            }
        });

        return redirect()->route('payroll.rates.index')->with('status', __('Tax table saved.'));
    }

    /**
     * @return Collection<int, string>
     */
    private function dates(): Collection
    {
        return TaxBracket::query()->select('effective_from')->distinct()->pluck('effective_from')
            ->map(fn ($date): string => $date->toDateString());
    }
}
