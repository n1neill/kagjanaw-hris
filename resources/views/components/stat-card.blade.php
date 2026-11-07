@props(['label', 'value', 'icon' => null, 'hint' => null, 'trend' => null, 'trendTone' => null, 'progress' => null, 'href' => null, 'alert' => false])

{{-- $trendTone: 'up' (green), 'down' (red) or null (muted). $progress is a 0-100 share drawn as a meter. $alert adds a yellow accent edge. --}}
@php
$tag = $href ? 'a' : 'div';
$trendColor = match ($trendTone) {
    'up' => 'text-green-700 dark:text-green-300',
    'down' => 'text-red-700 dark:text-red-300',
    default => 'text-ink-muted',
};
$classes = 'block rounded-xl border border-line bg-surface p-5 shadow-xs transition-all'
    .($href ? ' group hover:border-line-strong hover:shadow-sm focus-visible:outline-2 focus-visible:outline-brand-500' : '')
    .($alert ? ' border-l-4 border-l-yellow-500' : '');
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-ink-muted">{{ $label }}</p>
        @if ($icon)
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700 transition-colors group-hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400 dark:group-hover:bg-brand-500/20" aria-hidden="true">
                <x-icon :name="$icon" class="size-5" />
            </span>
        @endif
    </div>
    <p @class(['mt-2 text-3xl font-semibold tracking-tight tabular-nums', 'text-ink' => ! $alert, 'text-yellow-700 dark:text-yellow-300' => $alert])>{{ $value }}</p>
    @if ($trend)
        <p class="mt-1 text-xs font-medium {{ $trendColor }}">{{ $trend }}</p>
    @endif
    @if ($progress !== null)
        <x-meter :value="$progress" :max="100" :label="$label" class="mt-3" />
    @endif
    @if ($hint)
        <p class="mt-1.5 text-xs text-ink-muted">{{ $hint }}</p>
    @endif
</{{ $tag }}>
