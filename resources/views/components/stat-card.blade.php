@props(['label', 'value', 'icon' => null, 'hint' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-surface p-5 shadow-xs']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-ink-muted">{{ $label }}</p>
        @if ($icon)
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400" aria-hidden="true">
                <x-icon :name="$icon" class="size-5" />
            </span>
        @endif
    </div>
    <p class="mt-1 text-3xl font-semibold tracking-tight text-ink tabular-nums">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-ink-muted">{{ $hint }}</p>
    @endif
</div>
