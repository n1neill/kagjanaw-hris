<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('System Administration') }}</x-slot>

    <p class="text-sm text-ink-muted">{{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}</p>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('User accounts')" :value="$totalUsers" icon="users" />
        <x-stat-card :label="__('Active')" :value="$activeUsers" />
        <x-stat-card :label="__('Deactivated')" :value="$inactiveUsers" />
    </div>

    @include('dashboard.partials.announcements')

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Accounts by role')" class="lg:col-span-1">
            <x-slot name="actions">
                <x-button-link :href="route('admin.users.index')" variant="secondary">{{ __('Manage') }}</x-button-link>
            </x-slot>

            <dl class="divide-y divide-line">
                @foreach ($usersPerRole as $roleLabel => $count)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <dt class="text-ink-muted">{{ $roleLabel }}</dt>
                        <dd class="font-semibold text-ink">{{ $count }}</dd>
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
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2 text-sm last:border-0">
                        <a href="{{ route('admin.devices.show', $device) }}" class="font-medium text-ink hover:text-brand-700 dark:hover:text-brand-300">{{ $device->name }}</a>
                        <span class="text-ink-muted">
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
                    <p class="text-sm text-ink-muted">{{ __('No devices configured yet.') }}</p>
                @endforelse
            </x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-card :title="__('Backups')">
                    <x-slot name="actions">
                        <x-button-link :href="route('admin.backups.index')" variant="secondary">{{ __('Manage') }}</x-button-link>
                    </x-slot>

                    @if ($latestBackup)
                        <p class="text-sm text-ink">{{ __('Latest: :time', ['time' => $latestBackup->createdAt->diffForHumans()]) }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ $latestBackup->createdAt->format('M j, Y g:i A') }} · {{ $latestBackup->readableSize() }}</p>
                    @else
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">{{ __('No backup has been made yet.') }}</p>
                    @endif
                    <p class="mt-2 text-xs text-ink-muted">
                        {{ $backupEnabled ? __('Daily backup at :time.', ['time' => \Carbon\Carbon::createFromFormat('H:i', $backupTime)->format('g:i A')]) : __('The daily backup is turned off.') }}
                    </p>
                </x-card>

                <x-card :title="__('Security')">
                    <x-slot name="actions">
                        <x-button-link :href="route('admin.audit-logs.index', ['category' => 'security'])" variant="secondary">{{ __('Audit Log') }}</x-button-link>
                    </x-slot>

                    <dl class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-muted">{{ __('Failed sign-ins (24 hours)') }}</dt>
                            <dd @class(['font-semibold', 'text-yellow-700 dark:text-yellow-300' => $failedSignIns > 0, 'text-ink' => $failedSignIns === 0])>{{ $failedSignIns }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-muted">{{ __('Denied requests (24 hours)') }}</dt>
                            <dd @class(['font-semibold', 'text-yellow-700 dark:text-yellow-300' => $deniedRequests > 0, 'text-ink' => $deniedRequests === 0])>{{ $deniedRequests }}</dd>
                        </div>
                    </dl>
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
