{{-- The newest announcements addressed to the signed-in user. Expects $announcements. --}}
<div class="rounded-xl border border-line bg-surface p-4 shadow-xs">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-ink">{{ __('Announcements') }}</h3>
        <x-icon name="megaphone" class="size-5 text-brand-600 dark:text-brand-400" />
    </div>
    @forelse ($announcements as $announcement)
        <a href="{{ route('announcements.show', $announcement) }}" class="mt-3 block rounded-lg border border-line p-3 hover:border-brand-200 dark:hover:border-brand-800 hover:bg-brand-50/40 dark:hover:bg-brand-500/5">
            <span class="flex items-center gap-2">
                @if ($announcement->is_pinned)
                    <x-badge color="brand">{{ __('Pinned') }}</x-badge>
                @endif
                <span class="text-sm font-medium text-ink">{{ $announcement->title }}</span>
            </span>
            <span class="mt-1 block line-clamp-2 text-sm text-ink-muted">{{ $announcement->body }}</span>
            <span class="mt-1 block text-xs text-ink-muted">{{ $announcement->published_at->diffForHumans() }}</span>
        </a>
    @empty
        <p class="mt-2 text-sm text-ink-muted">{{ __('No announcements right now.') }}</p>
    @endforelse
    <div class="mt-3 flex flex-wrap gap-4">
        <a href="{{ route('announcements.index') }}" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ __('All announcements') }}</a>
        @can(\App\Enums\Permission::ManageAnnouncements)
            <a href="{{ route('announcements.create') }}" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ __('Post an announcement') }}</a>
        @endcan
    </div>
</div>
