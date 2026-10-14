<x-app-layout :title="__('Notifications')">
    <x-slot name="header">{{ __('Notifications') }}</x-slot>

    <x-card :title="__('All notifications')" :description="trans_choice('{0} Everything is read.|{1} :count unread.|[2,*] :count unread.', $unreadCount, ['count' => $unreadCount])">
        @if ($unreadCount > 0)
            <x-slot name="actions">
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <x-secondary-button type="submit">{{ __('Mark all as read') }}</x-secondary-button>
                </form>
            </x-slot>
        @endif

        <ul class="-my-2 divide-y divide-line">
            @forelse ($notifications as $notification)
                <li>
                    <a href="{{ route('notifications.open', $notification->id) }}" @class(['flex items-start justify-between gap-4 rounded-lg px-2 py-3 text-sm hover:bg-surface-muted', 'bg-brand-50/50 dark:bg-brand-500/5' => $notification->unread()])>
                        <span @class(['font-medium text-ink' => $notification->unread(), 'text-ink-muted' => ! $notification->unread()])>
                            @if ($notification->unread())
                                <span class="sr-only">{{ __('Unread:') }}</span>
                            @endif
                            {{ $notification->data['message'] ?? __('Notification') }}
                        </span>
                        <span class="shrink-0 text-xs text-ink-muted">{{ $notification->created_at->format('M j, Y g:i A') }}</span>
                    </a>
                </li>
            @empty
                <li class="py-3 text-sm text-ink-muted">{{ __('You have no notifications.') }}</li>
            @endforelse
        </ul>

        <div class="mt-4">{{ $notifications->links() }}</div>
    </x-card>
</x-app-layout>
