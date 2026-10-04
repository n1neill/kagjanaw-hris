@props(['title', 'icon' => null])

{{-- Dashboard placeholder for a module delivered in a later phase. --}}
<div {{ $attributes->merge(['class' => 'rounded-lg border border-dashed border-gray-300 bg-white p-4']) }}>
    <div class="flex items-center gap-2">
        @if ($icon)
            <x-icon :name="$icon" class="size-5 text-gray-400" />
        @endif
        <h3 class="text-sm font-semibold text-gray-700">{{ $title }}</h3>
    </div>
    <p class="mt-2 text-sm text-gray-500">{{ $slot }}</p>
</div>
