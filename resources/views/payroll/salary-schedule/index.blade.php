@use('App\Services\Payroll\Money')
@use('App\Services\Payroll\PayrollSettings')
@use('Carbon\CarbonImmutable')

<x-app-layout :title="__('Salary Schedule')">
    <x-slot name="header">{{ __('Salary Schedule') }}</x-slot>

    <x-card :title="__('Monthly Salary Schedule')"
            :description="__('National rates by salary grade and step, and the rates this LGU pays at :percent% (:class). Verify each tranche against the DBM issuance before use.', ['percent' => $percentage, 'class' => PayrollSettings::classLabel($incomeClass)])">
        <x-slot name="actions">
            @if ($selected)
                <x-button-link :href="route('payroll.salary-schedule.edit', $selected)" variant="secondary">
                    <x-icon name="pencil" class="size-4" />
                    {{ __('Edit amounts') }}
                </x-button-link>
            @endif
        </x-slot>

        @unless ($isConfirmed)
            <p class="mb-4 rounded-md border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                {{ __('The LGU income class is not yet confirmed.') }}
                <a href="{{ route('payroll.settings.edit') }}" class="font-medium underline">{{ __('Set it in Payroll Settings.') }}</a>
            </p>
        @endunless

        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
            <nav class="flex flex-wrap gap-2" aria-label="{{ __('Tranches') }}">
                @foreach ($dates as $date)
                    <a href="{{ route('payroll.salary-schedule.index', ['effective' => $date]) }}"
                       @class([
                           'rounded-md px-3 py-1.5 text-sm font-medium',
                           'bg-brand-700 text-white' => $date === $selected,
                           'border border-gray-300 text-gray-700 hover:bg-gray-50' => $date !== $selected,
                       ])>
                        {{ __('From :date', ['date' => CarbonImmutable::parse($date)->format('M j, Y')]) }}
                        @if ($date === $inForce)
                            <span class="ml-1 text-xs">({{ __('in force') }})</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <form method="POST" action="{{ route('payroll.salary-schedule.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div>
                    <x-input-label for="effective_from" :value="__('New tranche effective')" />
                    <x-text-input id="effective_from" name="effective_from" type="date" class="mt-1" :value="old('effective_from')" required />
                </div>
                <x-secondary-button type="submit">{{ __('Add tranche') }}</x-secondary-button>
            </form>
        </div>
        <x-input-error :messages="$errors->get('effective_from')" class="mb-4" />

        @if ($legalBasis)
            <p class="mb-4 text-sm text-gray-600">{{ __('Basis:') }} {{ $legalBasis }}</p>
        @endif

        @if ($grid->isEmpty())
            <p class="text-sm text-gray-500">{{ __('No Salary Schedule has been entered.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-3">{{ __('SG') }}</th>
                            @foreach (range(1, 8) as $step)
                                <th class="px-2 py-2 text-right">{{ __('Step :step', ['step' => $step]) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grid as $grade => $steps)
                            <tr class="border-b border-gray-100">
                                <th class="py-1.5 pr-3 text-left font-semibold text-gray-900">{{ $grade }}</th>
                                @foreach (range(1, 8) as $step)
                                    <td class="px-2 py-1.5 text-right tabular-nums">
                                        @isset($steps[$step])
                                            <span class="block text-gray-900">{{ Money::format($schedule->lguAmount(Money::toCentavos($steps[$step]))) }}</span>
                                            <span class="block text-xs text-gray-500">{{ Money::format($steps[$step]) }}</span>
                                        @endisset
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-500">{{ __('Upper figure: LGU rate (rounded to the peso). Lower figure: national rate.') }}</p>
        @endif
    </x-card>
</x-app-layout>
