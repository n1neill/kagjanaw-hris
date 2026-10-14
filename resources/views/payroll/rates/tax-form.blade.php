@use('Carbon\CarbonImmutable')

@php
    $rows = old('brackets', $brackets->map(fn ($bracket) => ['over' => $bracket->over, 'base_tax' => $bracket->base_tax, 'rate' => $bracket->rate])->all());
    $rows = array_pad(array_values($rows), max(count($rows) + 2, 8), ['over' => '', 'base_tax' => '', 'rate' => '']);
@endphp

<x-app-layout :title="__('Edit Tax Table')">
    <x-slot name="header">{{ __('Edit Tax Table') }}</x-slot>

    <x-card class="max-w-3xl" :title="__('Monthly withholding tax table effective :date', ['date' => CarbonImmutable::parse($effectiveFrom)->format('F j, Y')])"
            :description="__('One row per bracket. The first bracket starts at 0.00. Leave unused rows blank.')">
        <form method="POST" action="{{ route('payroll.rates.tax.update', $effectiveFrom) }}" class="space-y-5">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div role="alert" class="rounded-lg border border-red-200 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div>
                <x-input-label for="legal_basis" :value="__('Basis (law or revenue regulation)')" />
                <x-text-input id="legal_basis" name="legal_basis" class="mt-1" :value="old('legal_basis', $legalBasis)" />
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-ink-muted">
                        <th class="py-2 pr-2">{{ __('Monthly pay over (₱)') }}</th>
                        <th class="px-2 py-2">{{ __('Base tax (₱)') }}</th>
                        <th class="py-2 pl-2">{{ __('Rate on excess (%)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        <tr>
                            @foreach (['over' => 'pr-2', 'base_tax' => 'px-2', 'rate' => 'pl-2'] as $field => $padding)
                                <td class="py-1 {{ $padding }}">
                                    <x-text-input :name="'brackets['.$index.']['.$field.']'" inputmode="decimal" class="text-right" :value="$row[$field] ?? ''" :aria-label="__('Bracket :n, :field', ['n' => $index + 1, 'field' => str_replace('_', ' ', $field)])" />
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex items-center gap-3">
                <x-primary-button>{{ __('Save Table') }}</x-primary-button>
                <x-button-link :href="route('payroll.rates.index')" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
