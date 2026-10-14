@props(['type' => 'info', 'dismissible' => false])

@php
[$classes, $icon, $role] = match ($type) {
    'success' => ['border-green-200 bg-green-50 text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-200', 'check-circle', 'status'],
    'error' => ['border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200', 'exclamation-circle', 'alert'],
    'warning' => ['border-yellow-200 bg-yellow-50 text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-500/10 dark:text-yellow-200', 'exclamation-triangle', 'alert'],
    default => ['border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200', 'information-circle', 'status'],
};
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-xs '.$classes]) }}
     role="{{ $role }}"
     @if ($dismissible) x-data="{ show: true }" x-show="show" x-transition.opacity @endif>
    <x-icon :name="$icon" class="mt-px size-5 shrink-0" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
    @if ($dismissible)
        <button type="button" class="-m-1 rounded-md p-1 opacity-70 transition hover:opacity-100 focus-visible:outline-2 focus-visible:outline-current" @click="show = false">
            <span class="sr-only">{{ __('Dismiss') }}</span>
            <x-icon name="x" class="size-4" />
        </button>
    @endif
</div>
