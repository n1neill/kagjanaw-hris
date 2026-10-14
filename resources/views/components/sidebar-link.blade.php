@props(['active' => false, 'icon' => null])

@php
$classes = $active
    ? 'bg-brand-50 text-brand-800 dark:bg-brand-500/10 dark:text-brand-300'
    : 'text-ink-muted hover:bg-surface-muted hover:text-ink';

$iconClasses = $active
    ? 'text-brand-600 dark:text-brand-400'
    : 'text-ink-subtle group-hover:text-ink-muted';
@endphp

<a {{ $attributes->merge(['class' => 'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand-500 '.$classes]) }} @if ($active) aria-current="page" @endif>
    @if ($icon)
        <x-icon :name="$icon" class="size-5 shrink-0 {{ $iconClasses }}" />
    @endif
    <span class="truncate">{{ $slot }}</span>
</a>
