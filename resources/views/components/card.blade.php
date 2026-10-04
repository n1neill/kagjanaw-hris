@props(['title' => null, 'description' => null])

<section {{ $attributes->merge(['class' => 'rounded-lg border border-gray-200 bg-white shadow-xs']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 px-4 py-3 sm:px-6">
            <div>
                @if ($title)
                    <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-gray-500">{{ $description }}</p>
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
