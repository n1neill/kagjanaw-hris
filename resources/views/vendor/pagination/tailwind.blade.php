{{-- Laravel's Tailwind pagination, restyled with the application's theme tokens. --}}
@php
$base = 'inline-flex min-w-9 items-center justify-center rounded-lg px-3 py-2 text-sm font-medium transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500';
$link = $base.' text-ink-muted hover:bg-surface-muted hover:text-ink';
$disabled = $base.' cursor-not-allowed text-ink-subtle opacity-60';
$current = $base.' bg-brand-50 text-brand-800 ring-1 ring-inset ring-brand-300 dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-700';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-3">
        <p class="hidden text-sm text-ink-muted sm:block">
            {!! __('Showing') !!}
            @if ($paginator->firstItem())
                <span class="font-medium text-ink">{{ $paginator->firstItem() }}</span>
                {!! __('to') !!}
                <span class="font-medium text-ink">{{ $paginator->lastItem() }}</span>
            @else
                {{ $paginator->count() }}
            @endif
            {!! __('of') !!}
            <span class="font-medium text-ink">{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </p>

        <div class="flex w-full items-center justify-between gap-1 sm:w-auto sm:justify-end">
            @if ($paginator->onFirstPage())
                <span class="{{ $disabled }}" aria-disabled="true">
                    <x-icon name="chevron-right" class="size-4 rotate-180" />
                    <span class="sm:sr-only">{{ __('Previous') }}</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }}">
                    <x-icon name="chevron-right" class="size-4 rotate-180" />
                    <span class="sm:sr-only">{{ __('Previous') }}</span>
                </a>
            @endif

            <span class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="{{ $disabled }}" aria-disabled="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="{{ $current }}" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $link }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }}">
                    <span class="sm:sr-only">{{ __('Next') }}</span>
                    <x-icon name="chevron-right" class="size-4" />
                </a>
            @else
                <span class="{{ $disabled }}" aria-disabled="true">
                    <span class="sm:sr-only">{{ __('Next') }}</span>
                    <x-icon name="chevron-right" class="size-4" />
                </span>
            @endif
        </div>
    </nav>
@endif
