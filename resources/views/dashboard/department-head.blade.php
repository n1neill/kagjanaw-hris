<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('Department Dashboard') }}</x-slot>

    <p class="text-sm text-gray-600">{{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}</p>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-stat-card :label="$department?->name ?? __('No department assigned')" :value="$teamSize" icon="users" :hint="__('Employees in service')" />
        <div class="flex items-center justify-center rounded-lg border border-gray-200 bg-white p-4 shadow-xs">
            <x-button-link :href="route('employees.index')" variant="secondary">{{ __('View my department') }}</x-button-link>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-upcoming-module :title="__('Pending endorsements')" icon="calendar">
            {{ __('Leave requests from your department awaiting your endorsement (Phase 4).') }}
        </x-upcoming-module>
        <x-upcoming-module :title="__('Team attendance today')" icon="clock">
            {{ __('Who is in, late or absent in your department (Phase 3).') }}
        </x-upcoming-module>
        <x-upcoming-module :title="__('My leave balance')" icon="briefcase">
            {{ __('Your vacation and sick leave credits (Phase 4).') }}
        </x-upcoming-module>
    </div>
</x-app-layout>
