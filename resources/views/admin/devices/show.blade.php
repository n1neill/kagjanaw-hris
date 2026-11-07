@php($isKiosk = $device->receivesPushedPunches())

<x-app-layout :title="$device->name">
    <x-slot name="header">{{ __('Biometric Device') }}</x-slot>

    @if (session('kioskToken'))
        <div role="alert" class="rounded-lg border border-yellow-300 dark:border-yellow-500/30 bg-yellow-50 dark:bg-yellow-500/10 p-4">
            <div class="flex gap-3">
                <x-icon name="key" class="size-6 shrink-0 text-yellow-700 dark:text-yellow-300" />
                <div class="min-w-0 text-sm text-yellow-900 dark:text-yellow-200">
                    <p class="font-semibold">{{ __('Kiosk token') }}</p>
                    <p class="mt-1">{{ __('Enter this token in the kiosk app\'s settings on the kiosk PC. It is shown only once; if it is lost, issue a new one.') }}</p>
                    <p class="mt-3 break-all font-mono text-base font-semibold">{{ session('kioskToken') }}</p>
                </div>
            </div>
        </div>
    @endif

    <x-card :title="$device->name" :description="$device->location">
        <x-slot name="actions">
            <form method="POST" action="{{ route('admin.devices.test', $device) }}">
                @csrf
                <x-secondary-button type="submit">{{ __('Test Connection') }}</x-secondary-button>
            </form>
            @if ($isKiosk)
                <form method="POST" action="{{ route('admin.devices.token', $device) }}" data-confirm="{{ __('Issue a new kiosk token? The kiosk stops working until the new token is entered in it.') }}">
                    @csrf
                    <x-primary-button>{{ __('Issue Kiosk Token') }}</x-primary-button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.devices.sync', $device) }}">
                    @csrf
                    <x-primary-button>{{ __('Sync Now') }}</x-primary-button>
                </form>
            @endif
            <x-button-link :href="route('admin.devices.edit', $device)" variant="secondary">
                <x-icon name="pencil" class="size-4" />
                {{ __('Edit') }}
            </x-button-link>
        </x-slot>

        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @if ($isKiosk)
                <x-detail :label="__('Kiosk PC address')"><span class="font-mono">{{ $device->ip_address }}</span></x-detail>
                <x-detail :label="__('Driver')">{{ $device->driver }}</x-detail>
                <x-detail :label="__('Accepts punches')"><x-status-badge :active="$device->is_active" /></x-detail>
                <x-detail :label="__('Last check-in')">{{ $device->last_seen_at?->format('M j, Y g:i A') ?? __('Never') }}</x-detail>
                <x-detail :label="__('Kiosk token')">{{ $hasKioskToken ? __('Issued') : __('Not issued yet') }}</x-detail>
            @else
                <x-detail :label="__('Address')"><span class="font-mono">{{ $device->ip_address }}:{{ $device->port }}</span></x-detail>
                <x-detail :label="__('Driver')">{{ $device->driver }}</x-detail>
                <x-detail :label="__('Automatic sync')"><x-status-badge :active="$device->is_active" /></x-detail>
                <x-detail :label="__('Last successful sync')">{{ $device->last_synced_at?->format('M j, Y g:i A') ?? __('Never') }}</x-detail>
                <x-detail :label="__('Last attempt')">
                    @if ($device->last_sync_status)
                        <x-badge :color="$device->last_sync_status->badgeColor()">{{ $device->last_sync_status->label() }}</x-badge>
                    @endif
                </x-detail>
            @endif
        </dl>
    </x-card>

    <x-card
        :title="$isKiosk ? __('Received punches') : __('Sync log')"
        :description="$isKiosk
            ? __('Each batch of punches the kiosk sent, newest first. Duplicates are punches the kiosk re-sent after a dropped connection.')
            : __('Each synchronization attempt, newest first. Duplicates are punches already stored from an earlier sync.')"
    >
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line bg-surface-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                        <th class="px-3 py-2.5">{{ $isKiosk ? __('Received') : __('Started') }}</th>
                        <th class="px-3 py-2.5">{{ __('By') }}</th>
                        <th class="px-3 py-2.5">{{ __('Result') }}</th>
                        <th class="px-3 py-2.5 text-right">{{ __('Read') }}</th>
                        <th class="px-3 py-2.5 text-right">{{ __('New') }}</th>
                        <th class="px-3 py-2.5 text-right">{{ __('Duplicates') }}</th>
                        <th class="px-3 py-2.5 text-right">{{ __('Flagged') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr class="border-b border-line align-top transition-colors hover:bg-surface-muted/60">
                            <td class="whitespace-nowrap px-3 py-2.5">{{ $run->started_at->format('M j, Y g:i:s A') }}</td>
                            <td class="px-3 py-2.5 text-ink-muted">{{ $run->triggeredBy?->name ?? ($isKiosk ? __('Kiosk') : __('Scheduler')) }}</td>
                            <td class="px-3 py-2.5">
                                <x-badge :color="$run->status->badgeColor()">{{ $run->status->label() }}</x-badge>
                                @if ($run->error_message)
                                    <span class="mt-1 block text-xs text-red-700 dark:text-red-300">{{ $run->error_message }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $run->fetched_count }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $run->inserted_count }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $run->duplicate_count }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $run->flagged_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state icon="server" :title="$isKiosk ? __('The kiosk has not sent any punches yet.') : __('This device has not been synchronized yet.')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $runs->links() }}</div>
    </x-card>
</x-app-layout>
