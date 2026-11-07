@props(['href' => null])

{{-- One scannable row for a divide-y list: title and meta on the left, a badge or value on the right. --}}
@php
$tag = $href ? 'a' : 'div';
@endphp

<li class="list-none">
    <{{ $tag }}
        @if ($href) href="{{ $href }}" @endif
        {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 transition-colors sm:px-6'.($href ? ' hover:bg-surface-muted/60 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand-500' : '')]) }}
    >
        <span class="min-w-0">
            <span class="block truncate text-sm font-medium text-ink">{{ $slot }}</span>
            @isset($meta)
                <span class="block text-xs text-ink-muted">{{ $meta }}</span>
            @endisset
        </span>
        @isset($trailing)
            <span class="flex shrink-0 items-center gap-3 text-sm text-ink-muted">{{ $trailing }}</span>
        @endisset
    </{{ $tag }}>
</li>
