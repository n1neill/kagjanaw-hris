<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('Department Dashboard') }}</x-slot>

    <p class="text-sm text-ink-muted">{{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}</p>

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

    <a href="{{ route('leave.endorsements') }}" class="flex items-center justify-between gap-4 rounded-xl border border-line bg-surface p-4 shadow-xs hover:border-brand-300 dark:hover:border-brand-700">
        <div class="flex items-center gap-3">
            <x-icon name="inbox" class="size-6 text-brand-700 dark:text-brand-400" />
            <div>
                <p class="text-sm font-medium text-ink">{{ __('Leave applications waiting for your endorsement') }}</p>
                <p class="text-xs text-ink-muted">{{ __('Endorse or reject them with your remarks.') }}</p>
            </div>
        </div>
        <span class="text-2xl font-semibold {{ $pendingEndorsements ? 'text-yellow-700 dark:text-yellow-300' : 'text-ink' }}">{{ $pendingEndorsements }}</span>
    </a>

    @include('dashboard.partials.my-attendance')

    @include('dashboard.partials.my-leave')

    <div class="grid gap-4 sm:grid-cols-2">
        @include('dashboard.partials.my-payslip')
        @include('dashboard.partials.announcements')
    </div>
</x-app-layout>
