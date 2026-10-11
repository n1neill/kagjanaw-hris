<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('Department Dashboard') }}</x-slot>

    <p class="text-sm text-gray-600">{{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}</p>

    <x-card :title="__('Team attendance today')" :description="$department?->name ?? __('No department assigned')">
        <x-slot name="actions">
            <x-button-link :href="route('attendance.summary')" variant="secondary">{{ __('Department Attendance') }}</x-button-link>
            <x-button-link :href="route('employees.index')" variant="secondary">{{ __('My Department') }}</x-button-link>
        </x-slot>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-card :label="__('Employees in service')" :value="$teamSize" icon="users" />
            <x-stat-card :label="__('Reported today')" :value="$reportedToday" icon="clock" :hint="__('Based on the latest sync')" />
            <x-stat-card :label="__('Late today')" :value="$lateToday" />
        </div>
    </x-card>

    @include('dashboard.partials.my-attendance')

    <div class="grid gap-4 sm:grid-cols-2">
        <x-upcoming-module :title="__('Pending endorsements')" icon="calendar">
            {{ __('Leave requests from your department awaiting your endorsement (Phase 4).') }}
        </x-upcoming-module>
        <x-upcoming-module :title="__('My leave balance')" icon="briefcase">
            {{ __('Your vacation and sick leave credits (Phase 4).') }}
        </x-upcoming-module>
    </div>
</x-app-layout>
