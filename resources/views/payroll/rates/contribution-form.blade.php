@php($isNew = ! $rate->exists)

<x-app-layout :title="$isNew ? __('New Contribution Rate') : __('Edit Contribution Rate')">
    <x-slot name="header">{{ $isNew ? __('New Contribution Rate') : __('Edit Contribution Rate') }}</x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ $isNew ? route('payroll.rates.contributions.store') : route('payroll.rates.contributions.update', $rate) }}" class="space-y-5">
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="type" :value="__('Contribution')" />
                    <x-select-input id="type" name="type" class="mt-1" :options="$types" :selected="old('type', $rate->type?->value)" />
                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="effective_from" :value="__('Effective from')" />
                    <x-text-input id="effective_from" name="effective_from" type="date" class="mt-1" :value="old('effective_from', $rate->effective_from?->toDateString())" required />
                    <x-input-error :messages="$errors->get('effective_from')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="employee_rate" :value="__('Employee share (% of monthly salary)')" />
                    <x-text-input id="employee_rate" name="employee_rate" inputmode="decimal" class="mt-1" :value="old('employee_rate', $rate->employee_rate)" required />
                    <x-input-error :messages="$errors->get('employee_rate')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="employer_rate" :value="__('Employer share (% of monthly salary)')" />
                    <x-text-input id="employer_rate" name="employer_rate" inputmode="decimal" class="mt-1" :value="old('employer_rate', $rate->employer_rate)" required />
                    <x-input-error :messages="$errors->get('employer_rate')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="salary_floor" :value="__('Salary floor (optional, ₱)')" />
                    <x-text-input id="salary_floor" name="salary_floor" inputmode="decimal" class="mt-1" :value="old('salary_floor', $rate->salary_floor)" />
                    <x-input-error :messages="$errors->get('salary_floor')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="salary_ceiling" :value="__('Salary ceiling (optional, ₱)')" />
                    <x-text-input id="salary_ceiling" name="salary_ceiling" inputmode="decimal" class="mt-1" :value="old('salary_ceiling', $rate->salary_ceiling)" />
                    <x-input-error :messages="$errors->get('salary_ceiling')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="legal_basis" :value="__('Basis (law or circular)')" />
                <x-text-input id="legal_basis" name="legal_basis" class="mt-1" :value="old('legal_basis', $rate->legal_basis)" />
                <x-input-error :messages="$errors->get('legal_basis')" class="mt-2" />
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>{{ $isNew ? __('Add Rate') : __('Save Changes') }}</x-primary-button>
                <x-button-link :href="route('payroll.rates.index')" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
