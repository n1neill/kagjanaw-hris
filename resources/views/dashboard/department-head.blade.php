<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('Department Dashboard') }}</x-slot>

    <x-page-header :title="__('Welcome back, :name.', ['name' => Auth::user()->name])" :description="($department?->name ?? __('No department assigned')).' · '.now()->format('l, F j, Y')">
        <x-slot name="actions">
            <x-button-link :href="route('employees.index')" variant="secondary">{{ __('My Department') }}</x-button-link>
            <x-button-link :href="route('attendance.summary')" variant="secondary">{{ __('Department Attendance') }}</x-button-link>
        </x-slot>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('Employees in service')" :value="$teamSize" icon="users" />
        <x-stat-card :label="__('Reported today')" :value="$reportedToday" icon="clock" :hint="__('Based on the latest sync')" />
        <x-stat-card :label="__('Late today')" :value="$lateToday" icon="calendar" />
        <a href="{{ route('leave.endorsements') }}" class="group rounded-xl border border-line bg-surface p-5 shadow-xs transition hover:border-brand-300 focus-visible:outline-2 focus-visible:outline-brand-500 dark:hover:border-brand-700">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-medium text-ink-muted">{{ __('Leave applications waiting for your endorsement') }}</p>
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400" aria-hidden="true">
                    <x-icon name="inbox" class="size-5" />
                </span>
            </div>
            <p @class(['mt-1 text-3xl font-semibold tracking-tight tabular-nums', 'text-yellow-700 dark:text-yellow-300' => $pendingEndorsements, 'text-ink' => ! $pendingEndorsements])>{{ $pendingEndorsements }}</p>
            <p class="mt-1 text-xs font-medium text-brand-700 group-hover:underline dark:text-brand-400">{{ __('Endorse or reject them with your remarks.') }}</p>
        </a>
    </div>

    @if ($attendanceChart)
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card :title="__('Team attendance this month')" :description="__('Employees on time, late, on leave and absent on each working day so far.')" class="lg:col-span-2">
                <x-chart :data="$attendanceChart" :label="__('Team attendance this month by day')" :empty="__('Attendance appears here once the DTRs for this month are processed.')" />
            </x-card>

            <x-card :title="__('Team leave this year')" :description="__('Days of approved leave by leave type, :year.', ['year' => now()->year])">
                <x-chart :data="$leaveChart" :label="__('Team days of approved leave by leave type')" :empty="__('No leave has been approved this year yet.')" />
            </x-card>
        </div>
    @endif

    @include('dashboard.partials.my-attendance')

    @include('dashboard.partials.my-leave')

    <div class="grid gap-6 lg:grid-cols-2">
        @include('dashboard.partials.my-payslip')
        @include('dashboard.partials.announcements')
    </div>
</x-app-layout>
