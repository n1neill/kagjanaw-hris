@props(['variant' => 'primary'])

@php
$classes = match ($variant) {
    'secondary' => 'border border-line-strong bg-surface text-ink hover:bg-surface-muted',
    default => 'bg-brand-700 text-white hover:bg-brand-800',
};
@endphp

<a {{ $attributes->merge(['class' => 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold shadow-xs transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface '.$classes]) }}>
    {{ $slot }}
</a>
