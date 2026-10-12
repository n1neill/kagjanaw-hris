@use('App\Enums\PayBasis')
@php($isNew = ! $employmentType->exists)

<x-app-layout :title="$isNew ? __('New Employment Type') : __('Edit Employment Type')">
    <x-slot name="header">{{ $isNew ? __('New Employment Type') : __('Edit Employment Type') }}</x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ $isNew ? route('reference-data.employment-types.store') : route('reference-data.employment-types.update', $employmentType) }}" class="space-y-5">
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1" :value="old('name', $employmentType->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="code" :value="__('Code')" />
                    <x-text-input id="code" name="code" type="text" class="mt-1" maxlength="30" :value="old('code', $employmentType->code)" required />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="description" :value="__('Description (optional)')" />
                <x-textarea-input id="description" name="description" class="mt-1">{{ old('description', $employmentType->description) }}</x-textarea-input>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="max-w-md">
                <x-input-label for="pay_basis" :value="__('Paid by')" />
                <x-select-input id="pay_basis" name="pay_basis" class="mt-1" :options="PayBasis::options()" :selected="old('pay_basis', $employmentType->pay_basis?->value ?? PayBasis::SalaryGrade->value)" />
                <p class="mt-1 text-xs text-gray-500">{{ __('Also decides the payroll: regular, or Job Order / COS.') }}</p>
                <x-input-error :messages="$errors->get('pay_basis')" class="mt-2" />
            </div>

            <div>
                <x-checkbox name="earns_leave_credits" value="1" :checked="old('earns_leave_credits', $employmentType->earns_leave_credits ?? true)" :label="__('Earns vacation and sick leave credits')" />
                <p class="mt-1 text-xs text-gray-500">{{ __('Leave credits are earned under the CSC rules on leave. Elective officials and Job Order / Contract of Service workers do not earn them.') }}</p>
            </div>

            <x-checkbox name="is_active" value="1" :checked="old('is_active', $employmentType->is_active)" :label="__('Active')" />

            <div class="flex items-center gap-3">
                <x-primary-button>{{ $isNew ? __('Add Employment Type') : __('Save Changes') }}</x-primary-button>
                <x-button-link :href="route('reference-data.employment-types.index')" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
