@props(['label', 'value', 'icon' => null, 'hint' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-gray-200 bg-white p-4 shadow-xs']) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
        @if ($icon)
            <x-icon :name="$icon" class="size-5 text-brand-600" />
        @endif
    </div>
    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
