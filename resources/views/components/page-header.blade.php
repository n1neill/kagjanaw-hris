@props(['title' => null, 'description' => null])

{{-- The intro row of a page: an optional heading and description, with the page's main actions on the right. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        @if ($title)
            <h2 class="text-2xl font-semibold tracking-tight text-ink">{{ $title }}</h2>
        @endif
        @if ($description)
            <p @class(['max-w-3xl text-sm text-ink-muted', 'mt-1' => $title])>{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
