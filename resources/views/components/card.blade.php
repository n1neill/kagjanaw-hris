@props(['title' => null, 'description' => null])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-surface shadow-xs']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-4 py-4 sm:px-6">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-ink-muted">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="p-4 sm:p-6">
        {{ $slot }}
    </div>
</section>
