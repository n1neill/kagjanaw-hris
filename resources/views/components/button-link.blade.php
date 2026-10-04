@props(['variant' => 'primary'])

@php
$classes = match ($variant) {
    'secondary' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
    default => 'bg-brand-700 text-white hover:bg-brand-800',
};
@endphp

<a {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold shadow-xs transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 '.$classes]) }}>
    {{ $slot }}
</a>
