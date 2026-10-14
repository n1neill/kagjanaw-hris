@props(['icon' => 'inbox', 'title', 'description' => null])

{{-- Shown in place of an empty list; any call to action goes in the slot. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-4 py-10 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400" aria-hidden="true">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <p class="mt-4 text-sm font-semibold text-ink">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-ink-muted">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
