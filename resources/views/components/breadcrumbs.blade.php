@props(['items' => []])

{{-- Parent pages for the top bar's breadcrumb slot, as [label => url]; the current page is the layout header. --}}
<ol class="flex items-center gap-1">
    @foreach ($items as $label => $url)
        <li class="flex items-center gap-1">
            <a href="{{ $url }}" class="rounded hover:text-brand-700 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500 dark:hover:text-brand-400">{{ $label }}</a>
            <x-icon name="chevron-right" class="size-3 text-ink-subtle" />
        </li>
    @endforeach
</ol>
