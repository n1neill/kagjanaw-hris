<x-app-layout :title="__('Biometric Devices')">
    <x-slot name="header">{{ __('Biometric Devices') }}</x-slot>

    <x-card :title="__('Time clocks')" :description="__('Active devices are synchronized automatically every :minutes minutes while the scheduler is running.', ['minutes' => $syncInterval])">
        <x-slot name="actions">
            <x-button-link :href="route('admin.devices.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('New Device') }}
            </x-button-link>
        </x-slot>

        <x-data-table :empty="__('No devices yet.')">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Address') }}</th>
                    <th>{{ __('Driver') }}</th>
                    <th>{{ __('Last sync') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th data-orderable="false"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($devices as $device)
                    <tr>
                        <td>
                            <span class="font-medium text-gray-900">{{ $device->name }}</span>
                            <span class="block text-xs text-gray-500">{{ $device->location }}</span>
                        </td>
                        <td class="font-mono text-xs">{{ $device->ip_address }}:{{ $device->port }}</td>
                        <td>{{ $device->driver }}</td>
                        <td data-order="{{ $device->last_synced_at?->timestamp ?? 0 }}">
                            {{ $device->last_synced_at?->diffForHumans() ?? __('Never') }}
                            @if ($device->last_sync_status)
                                <x-badge :color="$device->last_sync_status->badgeColor()">{{ $device->last_sync_status->label() }}</x-badge>
                            @endif
                        </td>
                        <td><x-status-badge :active="$device->is_active" /></td>
                        <td class="text-right">
                            <a href="{{ route('admin.devices.show', $device) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Open') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
