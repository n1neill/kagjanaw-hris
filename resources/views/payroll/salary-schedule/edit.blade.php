@use('Carbon\CarbonImmutable')

<x-app-layout :title="__('Edit Salary Schedule')">
    <x-slot name="header">{{ __('Edit Salary Schedule') }}</x-slot>

    <x-card :title="__('National rates effective :date', ['date' => CarbonImmutable::parse($effectiveFrom)->format('F j, Y')])"
            :description="__('Enter the monthly national rates in pesos exactly as issued by DBM. Leave a step blank if the grade has no such step.')">
        <form method="POST" action="{{ route('payroll.salary-schedule.update', $effectiveFrom) }}" class="space-y-5">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ __('Please correct the highlighted amounts.') }}
                </div>
            @endif

            <div class="max-w-2xl">
                <x-input-label for="legal_basis" :value="__('Basis (issuance)')" />
                <x-text-input id="legal_basis" name="legal_basis" class="mt-1" :value="old('legal_basis', $legalBasis)" />
                <x-input-error :messages="$errors->get('legal_basis')" class="mt-2" />
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-3">{{ __('SG') }}</th>
                            @foreach (range(1, 8) as $step)
                                <th class="px-1 py-2">{{ __('Step :step', ['step' => $step]) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (range(1, 33) as $grade)
                            <tr>
                                <th class="py-1 pr-3 text-left font-semibold text-gray-900">{{ $grade }}</th>
                                @foreach (range(1, 8) as $step)
                                    <td class="px-1 py-1">
                                        <input type="text" inputmode="decimal" name="amounts[{{ $grade }}][{{ $step }}]"
                                               value="{{ old('amounts.'.$grade.'.'.$step, $grid[$grade][$step] ?? '') }}"
                                               aria-label="{{ __('SG :grade Step :step', ['grade' => $grade, 'step' => $step]) }}"
                                               @class([
                                                   'block w-28 rounded-md text-right text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500',
                                                   'border-red-400' => $errors->has('amounts.'.$grade.'.'.$step),
                                                   'border-gray-300' => ! $errors->has('amounts.'.$grade.'.'.$step),
                                               ])>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>{{ __('Save Schedule') }}</x-primary-button>
                <x-button-link :href="route('payroll.salary-schedule.index', ['effective' => $effectiveFrom])" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
