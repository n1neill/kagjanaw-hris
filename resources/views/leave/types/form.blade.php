@php($isNew = ! $type->exists)

<x-app-layout :title="$isNew ? __('New Leave Type') : __('Edit Leave Type')">
    <x-slot name="header">{{ $isNew ? __('New Leave Type') : __('Edit Leave Type') }}</x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ $isNew ? route('leave.types.store') : route('leave.types.update', $type) }}" class="space-y-5">
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            @if ($isFixed)
                <p class="rounded-lg bg-surface-muted px-3 py-2 text-sm text-ink-muted">{{ __('This type drives the leave card. Its code and the credits it is charged to cannot be changed, and it cannot be deactivated.') }}</p>
            @endif

            <div class="grid gap-5 sm:grid-cols-4">
                <div class="sm:col-span-3">
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" class="mt-1" :value="old('name', $type->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="code" :value="__('Code')" />
                    <x-text-input id="code" name="code" class="mt-1 font-mono uppercase" maxlength="10" :value="old('code', $type->code)" :disabled="$isFixed" required />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="description" :value="__('Legal basis and rules')" />
                <x-textarea-input id="description" name="description" rows="3" class="mt-1">{{ old('description', $type->description) }}</x-textarea-input>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <div>
                    <x-input-label for="credit_source" :value="__('Charged to')" />
                    @if ($isFixed)
                        <input type="hidden" name="credit_source" value="{{ $type->credit_source->value }}">
                    @endif
                    <x-select-input id="credit_source" :name="$isFixed ? null : 'credit_source'" class="mt-1" :options="$sources" :selected="old('credit_source', $type->credit_source?->value)" :disabled="$isFixed" />
                    <x-input-error :messages="$errors->get('credit_source')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="annual_days" :value="__('Days per year (optional)')" />
                    <x-text-input id="annual_days" name="annual_days" inputmode="decimal" class="mt-1" :value="old('annual_days', $type->annual_days)" />
                    <p class="mt-1 text-xs text-ink-muted">{{ __('Required for an own yearly allotment.') }}</p>
                    <x-input-error :messages="$errors->get('annual_days')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="max_days_per_request" :value="__('Days per application (optional)')" />
                    <x-text-input id="max_days_per_request" name="max_days_per_request" inputmode="decimal" class="mt-1" :value="old('max_days_per_request', $type->max_days_per_request)" />
                    <x-input-error :messages="$errors->get('max_days_per_request')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="advance_notice_days" :value="__('File at least (days ahead)')" />
                    <x-text-input id="advance_notice_days" name="advance_notice_days" type="number" min="0" max="60" class="mt-1" :value="old('advance_notice_days', $type->advance_notice_days ?? 0)" required />
                    <x-input-error :messages="$errors->get('advance_notice_days')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="eligible_sex" :value="__('Available to')" />
                    <x-select-input id="eligible_sex" name="eligible_sex" class="mt-1" :options="$sexes" :selected="old('eligible_sex', $type->eligible_sex?->value)" :placeholder="__('All employees')" />
                    <x-input-error :messages="$errors->get('eligible_sex')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="attachment_required_over_days" :value="__('Attachment required over (days)')" />
                    <x-text-input id="attachment_required_over_days" name="attachment_required_over_days" type="number" min="0" class="mt-1" :value="old('attachment_required_over_days', $type->attachment_required_over_days)" />
                    <p class="mt-1 text-xs text-ink-muted">{{ __('0 = always; blank = never.') }}</p>
                    <x-input-error :messages="$errors->get('attachment_required_over_days')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="attachment_hint" :value="__('What to attach')" />
                <x-text-input id="attachment_hint" name="attachment_hint" class="mt-1" :value="old('attachment_hint', $type->attachment_hint)" />
                <x-input-error :messages="$errors->get('attachment_hint')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-3">
                <x-checkbox name="counts_calendar_days" value="1" :checked="old('counts_calendar_days', $type->counts_calendar_days)" :label="__('Counted in calendar days')" />
                <x-checkbox name="allows_half_day" value="1" :checked="old('allows_half_day', $type->allows_half_day)" :label="__('May be taken by half day')" />
                <x-checkbox name="allows_past_dates" value="1" :checked="old('allows_past_dates', $type->allows_past_dates)" :label="__('May be filed after the leave')" />
                <x-checkbox name="is_active" value="1" :checked="old('is_active', $type->is_active)" :label="__('Active')" />
            </div>
            <x-input-error :messages="$errors->get('is_active')" class="mt-2" />

            <div class="flex items-center gap-3">
                <x-primary-button>{{ $isNew ? __('Add Leave Type') : __('Save Changes') }}</x-primary-button>
                <x-button-link :href="route('leave.types.index')" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
