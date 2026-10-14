<x-app-layout :title="$device->name">
    <x-slot name="header">{{ __('Biometric Device') }}</x-slot>

    <x-card :title="$device->name" :description="$device->location">
        <x-slot name="actions">
            <form method="POST" action="{{ route('admin.devices.test', $device) }}">
                @csrf
                <x-secondary-button type="submit">{{ __('Test Connection') }}</x-secondary-button>
            </form>
            <form method="POST" action="{{ route('admin.devices.sync', $device) }}">
                @csrf
                <x-primary-button>{{ __('Sync Now') }}</x-primary-button>
            </form>
            <x-button-link :href="route('admin.devices.edit', $device)" variant="secondary">
                <x-icon name="pencil" class="size-4" />
                {{ __('Edit') }}
            </x-button-link>
        </x-slot>

        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-detail :label="__('Address')"><span class="font-mono">{{ $device->ip_address }}:{{ $device->port }}</span></x-detail>
            <x-detail :label="__('Driver')">{{ $device->driver }}</x-detail>
            <x-detail :label="__('Automatic sync')"><x-status-badge :active="$device->is_active" /></x-detail>
            <x-detail :label="__('Last successful sync')">{{ $device->last_synced_at?->format('M j, Y g:i A') ?? __('Never') }}</x-detail>
            <x-detail :label="__('Last attempt')">
                @if ($device->last_sync_status)
                    <x-badge :color="$device->last_sync_status->badgeColor()">{{ $device->last_sync_status->label() }}</x-badge>
                @endif
            </x-detail>
        </dl>
    </x-card>

    <x-card :title="__('Sync log')" :description="__('Each synchronization attempt, newest first. Duplicates are punches already stored from an earlier sync.')">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                        <th class="py-2 pr-3">{{ __('Started') }}</th>
                        <th class="px-2 py-2">{{ __('By') }}</th>
                        <th class="px-2 py-2">{{ __('Result') }}</th>
                        <th class="px-2 py-2 text-right">{{ __('Read') }}</th>
                        <th class="px-2 py-2 text-right">{{ __('New') }}</th>
                        <th class="px-2 py-2 text-right">{{ __('Duplicates') }}</th>
                        <th class="px-2 py-2 text-right">{{ __('Flagged') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr class="border-b border-line align-top">
                            <td class="whitespace-nowrap py-2 pr-3">{{ $run->started_at->format('M j, Y g:i:s A') }}</td>
                            <td class="px-2 py-2 text-ink-muted">{{ $run->triggeredBy?->name ?? __('Scheduler') }}</td>
                            <td class="px-2 py-2">
                                <x-badge :color="$run->status->badgeColor()">{{ $run->status->label() }}</x-badge>
                                @if ($run->error_message)
                                    <span class="mt-1 block text-xs text-red-700 dark:text-red-300">{{ $run->error_message }}</span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-right">{{ $run->fetched_count }}</td>
                            <td class="px-2 py-2 text-right">{{ $run->inserted_count }}</td>
                            <td class="px-2 py-2 text-right">{{ $run->duplicate_count }}</td>
                            <td class="px-2 py-2 text-right">{{ $run->flagged_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"><x-empty-state icon="server" :title="__('This device has not been synchronized yet.')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $runs->links() }}</div>
    </x-card>
</x-app-layout>
