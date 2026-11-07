@props(['value', 'max' => 100, 'color' => 'brand', 'label' => null])

{{-- A thin progress track. The value is clamped to 0..max so a bad figure cannot overflow the bar. --}}
@php
$maximum = max((float) $max, 0);
$current = min(max((float) $value, 0), $maximum);
$percent = $maximum > 0 ? round($current / $maximum * 100, 1) : 0;
$fill = match ($color) {
    'yellow' => 'bg-yellow-500',
    'red' => 'bg-red-500',
    'green' => 'bg-green-500',
    default => 'bg-brand-600 dark:bg-brand-500',
};
@endphp

<div
    role="progressbar"
    aria-valuemin="0"
    aria-valuemax="{{ $maximum }}"
    aria-valuenow="{{ $current }}"
    @if ($label) aria-label="{{ $label }}" @endif
    {{ $attributes->merge(['class' => 'h-1.5 w-full overflow-hidden rounded-full bg-surface-muted ring-1 ring-inset ring-line']) }}
>
    <div class="h-full rounded-full transition-all {{ $fill }}" style="width: {{ $percent }}%"></div>
</div>
