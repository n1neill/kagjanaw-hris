@props(['active' => false, 'icon' => null])

@php
$classes = $active
    ? 'bg-brand-800 text-white'
    : 'text-brand-100 hover:bg-brand-800/60 hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => 'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition '.$classes]) }} @if ($active) aria-current="page" @endif>
    @if ($icon)
        <x-icon :name="$icon" class="size-5 shrink-0" />
    @endif
    <span>{{ $slot }}</span>
</a>
