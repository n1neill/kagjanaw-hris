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
        <x-stat-card :label="__('Reported today')" :value="$reportedToday" icon="clock" :hint="__('Based on the latest sync')" :progress="$teamSize > 0 ? min($reportedToday / $teamSize * 100, 100) : 0" />
        <x-stat-card :label="__('Late today')" :value="$lateToday" icon="calendar" />
        <x-stat-card :href="route('leave.endorsements')" :label="__('Leave applications waiting for your endorsement')" :value="$pendingEndorsements" icon="inbox" :alert="(bool) $pendingEndorsements" :hint="__('Endorse or reject them with your remarks.')" />
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
