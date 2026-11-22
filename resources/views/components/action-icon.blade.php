@props(['icon', 'label', 'href' => null, 'tone' => 'brand'])

@php
    $toneClasses = match ($tone) {
        'danger' => 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40',
        'success' => 'text-green-700 hover:bg-green-50 dark:text-green-300 dark:hover:bg-green-950/40',
        default => 'text-brand-700 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-950/40',
    };
    $classes = "inline-flex size-8 items-center justify-center rounded-md transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 {$toneClasses}";
@endphp

{{-- Icon-only action; the label stays available as tooltip and for screen readers. --}}
@if ($href)
    <a href="{{ $href }}" title="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
        <x-icon :name="$icon" class="size-5" />
        <span class="sr-only">{{ $label }}</span>
    </a>
@else
    <button type="submit" title="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
        <x-icon :name="$icon" class="size-5" />
        <span class="sr-only">{{ $label }}</span>
    </button>
@endif
