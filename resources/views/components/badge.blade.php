@props(['color' => 'gray'])

@php
$colors = match ($color) {
    'green' => 'bg-green-50 text-green-700 ring-green-600/20',
    'red' => 'bg-red-50 text-red-700 ring-red-600/20',
    'yellow' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
    'brand' => 'bg-brand-50 text-brand-700 ring-brand-700/10',
    default => 'bg-gray-50 text-gray-600 ring-gray-500/10',
};
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.$colors]) }}>
    {{ $slot }}
</span>
