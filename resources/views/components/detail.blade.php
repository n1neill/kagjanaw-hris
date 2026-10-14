@props(['label'])

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <dt class="text-xs font-medium uppercase tracking-wide text-ink-muted">{{ $label }}</dt>
    <dd class="mt-1 break-words text-sm text-ink">{{ $slot->isEmpty() ? '—' : $slot }}</dd>
</div>
