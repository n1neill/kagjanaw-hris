@use('App\Services\Payroll\Money')
@use('App\Services\Reports\PayrollSummaryReport')

<x-app-layout :title="__('Payroll Summary')">
    <x-slot name="header">{{ __('Payroll Summary') }}</x-slot>

    <x-card :title="__('Payroll Summary')" :description="$subtitle">
        <x-slot name="actions">
            <x-export-links route="reports.payroll.export" :parameters="array_filter(['year' => $year, 'month' => $month, 'pay_basis' => $payBasis])" />
        </x-slot>

        <form method="GET" action="{{ route('reports.payroll') }}" class="mb-6 flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="year" :value="__('Year')" />
                <x-text-input id="year" name="year" type="number" min="2000" max="2100" class="mt-1 w-28" :value="$year" />
            </div>
            <div>
                <x-input-label for="month" :value="__('Month')" />
                <x-select-input id="month" name="month" class="mt-1" :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\CarbonImmutable::create(2000, $m)->format('F')])->all()" :selected="$month" :placeholder="__('Whole year')" />
            </div>
            <div>
                <x-input-label for="pay_basis" :value="__('Payroll')" />
                <x-select-input id="pay_basis" name="pay_basis" class="mt-1" :options="$payBases" :selected="$payBasis" :placeholder="__('All payrolls')" />
            </div>
            <x-secondary-button type="submit">{{ __('Show') }}</x-secondary-button>
        </form>

        <dl class="mb-6 grid gap-4 sm:grid-cols-4">
            <x-stat-card :label="__('Employees paid')" :value="$totals['employees']" />
            <x-stat-card :label="__('Gross pay')" :value="'₱ '.Money::format(Money::toDecimal($totals['amounts']['gross_pay']))" />
            <x-stat-card :label="__('Total deductions')" :value="'₱ '.Money::format(Money::toDecimal($totals['amounts']['total_deductions']))" />
            <x-stat-card :label="__('Net pay')" :value="'₱ '.Money::format(Money::toDecimal($totals['amounts']['net_pay']))" />
        </dl>

        <h3 class="text-sm font-semibold text-ink">{{ __('By office') }}</h3>
        <div class="mt-2 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                        <th class="py-2 pr-3">{{ __('Office') }}</th>
                        <th class="px-2 py-2 text-right">{{ __('Employees') }}</th>
                        @foreach (PayrollSummaryReport::AMOUNTS as $heading)
                            <th class="whitespace-nowrap px-2 py-2 text-right">{{ __($heading) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($byOffice as $row)
                        <tr class="border-b border-line">
                            <td class="py-1.5 pr-3 text-ink">{{ $row['office'] }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums">{{ $row['employees'] }}</td>
                            @foreach ($row['amounts'] as $centavos)
                                <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums">{{ Money::format(Money::toDecimal($centavos)) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count(PayrollSummaryReport::AMOUNTS) + 2 }}" class="py-4 text-center text-ink-muted">{{ __('No released payroll in this period.') }}</td></tr>
                    @endforelse
                    @if ($byOffice->isNotEmpty())
                        <tr class="font-semibold">
                            <td class="py-1.5 pr-3">{{ __('Total') }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums">{{ $totals['employees'] }}</td>
                            @foreach ($totals['amounts'] as $centavos)
                                <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums">{{ Money::format(Money::toDecimal($centavos)) }}</td>
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if ($byRun->isNotEmpty())
            <h3 class="mt-8 text-sm font-semibold text-ink">{{ __('By payroll') }}</h3>
            <ul class="mt-2 divide-y divide-line text-sm">
                @foreach ($byRun as $row)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a href="{{ route('payroll.runs.show', $row['run']) }}" class="font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ $row['run']->title() }}</a>
                        <span class="tabular-nums text-ink-muted">{{ __('Net pay ₱ :amount', ['amount' => Money::format(Money::toDecimal($row['amounts']['net_pay']))]) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-app-layout>
