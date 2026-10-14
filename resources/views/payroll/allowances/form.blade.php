@php($isNew = ! $type->exists)

<x-app-layout :title="$isNew ? __('New Allowance') : __('Edit Allowance')">
    <x-slot name="header">{{ $isNew ? __('New Allowance') : __('Edit Allowance') }}</x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ $isNew ? route('payroll.allowances.store') : route('payroll.allowances.update', $type) }}" class="space-y-5">
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            <div class="grid gap-5 sm:grid-cols-4">
                <div class="sm:col-span-3">
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" class="mt-1" :value="old('name', $type->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="code" :value="__('Code')" />
                    <x-text-input id="code" name="code" class="mt-1 font-mono uppercase" maxlength="20" :value="old('code', $type->code)" required />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="description" :value="__('Basis and notes')" />
                <x-textarea-input id="description" name="description" rows="3" class="mt-1">{{ old('description', $type->description) }}</x-textarea-input>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="monthly_amount" :value="__('Monthly amount (₱)')" />
                    <x-text-input id="monthly_amount" name="monthly_amount" inputmode="decimal" class="mt-1" :value="old('monthly_amount', $type->monthly_amount)" required />
                    <p class="mt-1 text-xs text-ink-muted">{{ __('An employee\'s assignment may set a different amount.') }}</p>
                    <x-input-error :messages="$errors->get('monthly_amount')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="coverage" :value="__('Who receives it')" />
                    <x-select-input id="coverage" name="coverage" class="mt-1" :options="$coverages" :selected="old('coverage', $type->coverage?->value)" />
                    <x-input-error :messages="$errors->get('coverage')" class="mt-2" />
                </div>
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-3">
                <x-checkbox name="is_taxable" value="1" :checked="old('is_taxable', $type->is_taxable)" :label="__('Taxable')" />
                <x-checkbox name="reduced_for_unpaid_days" value="1" :checked="old('reduced_for_unpaid_days', $type->reduced_for_unpaid_days)" :label="__('Reduced for days without pay')" />
                <x-checkbox name="is_active" value="1" :checked="old('is_active', $type->is_active)" :label="__('Active')" />
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>{{ $isNew ? __('Add Allowance') : __('Save Changes') }}</x-primary-button>
                <x-button-link :href="route('payroll.allowances.index')" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
