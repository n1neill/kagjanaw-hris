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

        <div class="grid gap-4 sm:grid-cols-2 lg:col-span-2">
            <x-upcoming-module :title="__('Biometric device status')" icon="clock">
                {{ __('Device configuration, last sync and sync errors will appear here (Phase 3).') }}
            </x-upcoming-module>
            <x-upcoming-module :title="__('Database backups')" icon="server">
                {{ __('Latest backup and restore tools will appear here (Phase 6).') }}
            </x-upcoming-module>
        </div>
    </div>
</x-app-layout>
