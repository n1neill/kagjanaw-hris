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

        <ul class="-my-2 divide-y divide-gray-100">
            @forelse ($notifications as $notification)
                <li>
                    <a href="{{ route('notifications.open', $notification->id) }}" @class(['flex items-start justify-between gap-4 rounded-md px-2 py-3 text-sm hover:bg-gray-50', 'bg-brand-50/50' => $notification->unread()])>
                        <span @class(['font-medium text-gray-900' => $notification->unread(), 'text-gray-600' => ! $notification->unread()])>
                            @if ($notification->unread())
                                <span class="sr-only">{{ __('Unread:') }}</span>
                            @endif
                            {{ $notification->data['message'] ?? __('Notification') }}
                        </span>
                        <span class="shrink-0 text-xs text-gray-500">{{ $notification->created_at->format('M j, Y g:i A') }}</span>
                    </a>
                </li>
            @empty
                <li class="py-3 text-sm text-gray-500">{{ __('You have no notifications.') }}</li>
            @endforelse
        </ul>

        <div class="mt-4">{{ $notifications->links() }}</div>
    </x-card>
</x-app-layout>
