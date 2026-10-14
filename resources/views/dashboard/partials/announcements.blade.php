{{-- The newest announcements addressed to the signed-in user. Expects $announcements. --}}
<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-xs">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-700">{{ __('Announcements') }}</h3>
        <x-icon name="megaphone" class="size-5 text-brand-600" />
    </div>
    @forelse ($announcements as $announcement)
        <a href="{{ route('announcements.show', $announcement) }}" class="mt-3 block rounded-md border border-gray-100 p-3 hover:border-brand-200 hover:bg-brand-50/40">
            <span class="flex items-center gap-2">
                @if ($announcement->is_pinned)
                    <x-badge color="brand">{{ __('Pinned') }}</x-badge>
                @endif
                <span class="text-sm font-medium text-gray-900">{{ $announcement->title }}</span>
            </span>
            <span class="mt-1 block line-clamp-2 text-sm text-gray-600">{{ $announcement->body }}</span>
            <span class="mt-1 block text-xs text-gray-500">{{ $announcement->published_at->diffForHumans() }}</span>
        </a>
    @empty
        <p class="mt-2 text-sm text-gray-500">{{ __('No announcements right now.') }}</p>
    @endforelse
    <div class="mt-3 flex flex-wrap gap-4">
        <a href="{{ route('announcements.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('All announcements') }}</a>
        @can(\App\Enums\Permission::ManageAnnouncements)
            <a href="{{ route('announcements.create') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Post an announcement') }}</a>
        @endcan
    </div>
</div>
