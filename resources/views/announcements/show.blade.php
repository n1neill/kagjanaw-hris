<x-app-layout :title="$announcement->title">
    <x-slot name="header">{{ __('Announcement') }}</x-slot>

    <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-xs sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ $announcement->title }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $announcement->published_at?->format('F j, Y g:i A') ?? __('Not published') }}
                    · {{ $announcement->author?->name ?? __('HR Office') }}
                </p>
            </div>
            @can('update', $announcement)
                <div class="flex flex-wrap items-center gap-2">
                    @include('announcements.partials.status')
                    <x-button-link :href="route('announcements.edit', $announcement)" variant="secondary">{{ __('Edit') }}</x-button-link>
                    <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" data-confirm="{{ __('Delete this announcement?') }}">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>{{ __('Delete') }}</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>

        <div class="mt-6 whitespace-pre-line text-sm leading-6 text-gray-800">{{ $announcement->body }}</div>

        @can('update', $announcement)
            <dl class="mt-6 grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-3">
                <x-detail :label="__('Addressed to')">
                    {{ $announcement->audience === \App\Enums\AnnouncementAudience::Everyone ? $announcement->audience->label() : $announcement->departments->pluck('name')->join(', ') }}
                </x-detail>
                <x-detail :label="__('Active accounts reached')">{{ $recipientCount }}</x-detail>
                <x-detail :label="__('Shown until')">{{ $announcement->expires_on?->format('F j, Y') ?? __('No expiry') }}</x-detail>
            </dl>
        @endcan
    </article>

    <div>
        <x-button-link :href="route('announcements.index')" variant="secondary">{{ __('All announcements') }}</x-button-link>
    </div>
</x-app-layout>
