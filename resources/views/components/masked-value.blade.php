@props(['value'])

{{-- Shows only the last four digits until the viewer chooses to reveal the full number. --}}
@if (filled($value))
    <span x-data="{ revealed: false }" class="inline-flex items-center gap-2 font-mono">
        <span x-show="! revealed">•••• {{ mb_substr($value, -4) }}</span>
        <span x-cloak x-show="revealed">{{ $value }}</span>
        <button type="button" class="text-xs font-sans font-medium text-brand-700 hover:text-brand-900" @click="revealed = ! revealed" x-text="revealed ? @js(__('Hide')) : @js(__('Show'))">{{ __('Show') }}</button>
    </span>
@endif
