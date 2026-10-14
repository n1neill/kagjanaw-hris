@props(['color' => 'gray'])

{{-- Status colours carry a dot so their meaning does not rely on colour alone; brand badges are labels. --}}
@php
$colors = match ($color) {
    'green' => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/25',
    'red' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/25',
    'yellow' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/25 dark:bg-yellow-500/10 dark:text-yellow-200 dark:ring-yellow-500/25',
    'blue' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/25',
    'brand' => 'bg-brand-50 text-brand-800 ring-brand-700/15 dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-500/25',
    default => 'bg-surface-muted text-ink-muted ring-line-strong',
};
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$colors]) }}>
    @if ($color !== 'brand')
        <span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
