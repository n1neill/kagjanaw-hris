<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('System Administration') }}</x-slot>

    <p class="text-sm text-gray-600">{{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}</p>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('User accounts')" :value="$totalUsers" icon="users" />
        <x-stat-card :label="__('Active')" :value="$activeUsers" />
        <x-stat-card :label="__('Deactivated')" :value="$inactiveUsers" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Accounts by role')" class="lg:col-span-1">
            <x-slot name="actions">
                <x-button-link :href="route('admin.users.index')" variant="secondary">{{ __('Manage') }}</x-button-link>
            </x-slot>

            <dl class="divide-y divide-gray-100">
                @foreach ($usersPerRole as $roleLabel => $count)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <dt class="text-gray-600">{{ $roleLabel }}</dt>
                        <dd class="font-semibold text-gray-900">{{ $count }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>

        <div class="grid gap-4 lg:col-span-2">
            <x-card :title="__('Biometric devices')">
                <x-slot name="actions">
                    <x-button-link :href="route('admin.devices.index')" variant="secondary">{{ __('Manage') }}</x-button-link>
                </x-slot>

                @forelse ($devices as $device)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 py-2 text-sm last:border-0">
                        <a href="{{ route('admin.devices.show', $device) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $device->name }}</a>
                        <span class="text-gray-600">
                            {{ $device->last_synced_at ? __('Synced :time', ['time' => $device->last_synced_at->diffForHumans()]) : __('Never synced') }}
                            @if ($device->last_sync_status)
                                <x-badge :color="$device->last_sync_status->badgeColor()">{{ $device->last_sync_status->label() }}</x-badge>
                            @endif
                            @unless ($device->is_active)
                                <x-badge>{{ __('Inactive') }}</x-badge>
                            @endunless
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">{{ __('No devices configured yet.') }}</p>
                @endforelse
            </x-card>
            <x-upcoming-module :title="__('Database backups')" icon="server">
                {{ __('Latest backup and restore tools will appear here (Phase 6).') }}
            </x-upcoming-module>
        </div>
    </div>
</x-app-layout>
