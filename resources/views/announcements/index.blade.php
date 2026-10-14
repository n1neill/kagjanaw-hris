<x-app-layout :title="__('Announcements')">
    <x-slot name="header">{{ __('Announcements') }}</x-slot>

    <x-page-header :description="__('News and notices from the HR Office.')">
        @if ($canManage)
            <x-slot name="actions">
                <x-button-link :href="route('announcements.create')">
                    <x-icon name="plus" class="size-4" />
                    {{ __('New Announcement') }}
                </x-button-link>
            </x-slot>
        @endif
    </x-page-header>

    <div class="space-y-4">
        @forelse ($announcements as $announcement)
            <article class="rounded-xl border border-line bg-surface p-4 shadow-xs sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h2 class="text-base font-semibold text-ink">
                        <a href="{{ route('announcements.show', $announcement) }}" class="hover:text-brand-700 dark:hover:text-brand-300">{{ $announcement->title }}</a>
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        @if ($announcement->is_pinned)
                            <x-badge color="brand">{{ __('Pinned') }}</x-badge>
                        @endif
                        @if ($canManage)
                            @include('announcements.partials.status')
                        @endif
                    </div>
                </div>
                <p class="mt-1 text-xs text-ink-muted">
                    {{ $announcement->published_at?->format('M j, Y g:i A') ?? __('Not published') }}
                    · {{ $announcement->author?->name ?? __('HR Office') }}
                    @if ($canManage)
                        · {{ $announcement->audience === \App\Enums\AnnouncementAudience::Everyone ? $announcement->audience->label() : $announcement->departments->pluck('name')->join(', ') }}
                    @endif
                </p>
                <p class="mt-3 line-clamp-3 whitespace-pre-line text-sm text-ink">{{ $announcement->body }}</p>
                <a href="{{ route('announcements.show', $announcement) }}" class="mt-2 inline-block text-sm font-medium text-brand-700 dark:text-brand-400 hover:underline">{{ __('Read more') }}</a>
            </article>
        @empty
            <x-card>
                <x-empty-state icon="megaphone" :title="__('There are no announcements right now.')" />
            </x-card>
        @endforelse
    </div>

    <div>{{ $announcements->links() }}</div>
</x-app-layout>
