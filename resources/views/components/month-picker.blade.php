@props(['month', 'action'])

{{-- A GET form for choosing a month (YYYY-MM). Extra hidden inputs go in the slot. --}}
<form method="GET" action="{{ $action }}" {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-3']) }}>
    {{ $slot }}
    <div>
        <x-input-label for="month" :value="__('Month')" />
        <x-text-input id="month" name="month" type="month" class="mt-1 w-44" :value="$month->format('Y-m')" max="{{ now()->format('Y-m') }}" required />
        <x-input-error :messages="$errors->get('month')" class="mt-2" />
    </div>
    <x-secondary-button type="submit">{{ __('Show') }}</x-secondary-button>
</form>
