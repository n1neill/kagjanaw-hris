@use('App\Services\Payroll\Money')

<x-app-layout :title="__('Contribution and Tax Rates')">
    <x-slot name="header">{{ __('Contribution and Tax Rates') }}</x-slot>

    <x-card :title="__('Government contributions')" :description="__('A contribution is the rate times the monthly basic salary, kept within the floor and ceiling. Enter a new rate with its effectivity date when GSIS, PhilHealth or Pag-IBIG change them; payrolls use the rate in force on the first day of the period. Verify the seeded rates against current circulars.')">
        <x-slot name="actions">
            <x-button-link :href="route('payroll.rates.contributions.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('New Rate') }}
            </x-button-link>
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-xs uppercase tracking-wide text-ink-muted">
                        <th class="py-2 pr-3">{{ __('Contribution') }}</th>
                        <th class="px-3 py-2">{{ __('Effective') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Employee') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Employer') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Salary floor') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Salary ceiling') }}</th>
                        <th class="px-3 py-2">{{ __('Basis') }}</th>
                        <th class="py-2 pl-3 text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($types as $type)
                        @foreach ($rates->get($type->value, collect()) as $rate)
                            <tr class="border-b border-line align-top">
                                <td class="py-2 pr-3 font-medium text-ink">{{ $loop->first ? $type->label() : '' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $rate->effective_from->format('M j, Y') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $rate->employee_rate }}%</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $rate->employer_rate }}%</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $rate->salary_floor !== null ? Money::format($rate->salary_floor) : '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $rate->salary_ceiling !== null ? Money::format($rate->salary_ceiling) : '—' }}</td>
                                <td class="px-3 py-2 text-xs text-ink-muted">{{ $rate->legal_basis }}</td>
                                <td class="py-2 pl-3 text-right">
                                    <x-action-icon icon="pencil" :label="__('Edit')" href="{{ route('payroll.rates.contributions.edit', $rate) }}" />
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card :title="__('Withholding tax on compensation')" :description="__('BIR monthly table: tax on monthly taxable compensation over a bracket\'s lower bound is its base tax plus its rate on the excess. Each semi-monthly payroll withholds half the tax on twice its taxable pay.')">
        <x-slot name="actions">
            <form method="POST" action="{{ route('payroll.rates.tax.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div>
                    <x-input-label for="effective_from" :value="__('New table effective')" />
                    <x-text-input id="effective_from" name="effective_from" type="date" class="mt-1" :value="old('effective_from')" required />
                </div>
                <x-secondary-button type="submit">{{ __('Add table') }}</x-secondary-button>
            </form>
        </x-slot>
        <x-input-error :messages="$errors->get('effective_from')" class="mb-4" />

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($taxTables as $effectiveFrom => $brackets)
                <div class="flex flex-col rounded-lg border border-line p-4">
                    <h3 class="text-sm font-semibold text-ink">{{ __('Effective :date', ['date' => $brackets->first()->effective_from->format('F j, Y')]) }}</h3>
                    @if ($brackets->first()->legal_basis)
                        <p class="mt-1 text-xs text-ink-muted">{{ $brackets->first()->legal_basis }}</p>
                    @endif
                    <table class="mt-3 w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-ink-muted">
                                <th class="py-1">{{ __('Over') }}</th>
                                <th class="py-1 text-right">{{ __('Base tax') }}</th>
                                <th class="py-1 text-right">{{ __('Rate on excess') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($brackets as $bracket)
                                <tr class="border-t border-line">
                                    <td class="py-1 tabular-nums">{{ Money::format($bracket->over) }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ Money::format($bracket->base_tax) }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ $bracket->rate }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="mt-auto flex items-center justify-end gap-2 border-t border-line pt-4">
                        <form method="POST" action="{{ route('payroll.rates.tax.destroy', $effectiveFrom) }}" data-confirm="{{ __('Remove the tax table effective :date?', ['date' => $brackets->first()->effective_from->format('F j, Y')]) }}">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>{{ __('Remove') }}</x-danger-button>
                        </form>
                        <x-button-link variant="secondary" href="{{ route('payroll.rates.tax.edit', $effectiveFrom) }}">{{ __('Edit') }}</x-button-link>
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>
</x-app-layout>
