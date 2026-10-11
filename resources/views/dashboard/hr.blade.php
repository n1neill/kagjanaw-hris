<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('HR Dashboard') }}</x-slot>

    <p class="text-sm text-gray-600">{{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}</p>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Employees in service')" :value="$employeesInService" icon="users" />
        <x-stat-card :label="__('Separated employees')" :value="$separatedEmployees" />
        <div class="flex items-center justify-center rounded-lg border border-gray-200 bg-white p-4 shadow-xs">
            <x-button-link :href="route('employees.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('New Employee') }}
            </x-button-link>
        </div>
    </div>

    <x-card :title="__('Attendance')">
        <x-slot name="actions">
            <x-button-link :href="route('attendance.summary')" variant="secondary">{{ __('Attendance Summary') }}</x-button-link>
        </x-slot>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-sm font-medium text-gray-500">{{ __('Last device sync') }}</p>
                @if ($latestSync)
                    <p class="mt-2 text-sm text-gray-900">{{ $latestSync->device->name }}</p>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ $latestSync->started_at->diffForHumans() }}
                        <x-badge :color="$latestSync->status->badgeColor()">{{ $latestSync->status->label() }}</x-badge>
                    </p>
                @else
                    <p class="mt-2 text-sm text-gray-500">{{ __('No device has been synchronized yet.') }}</p>
                @endif
            </div>
            <a href="{{ route('attendance.logs', ['status' => 'unknown-user']) }}" class="block rounded-lg border border-gray-200 p-4 hover:border-brand-300">
                <p class="text-sm font-medium text-gray-500">{{ __('Flagged punches') }}</p>
                <p class="mt-2 text-2xl font-semibold {{ $flaggedLogs ? 'text-yellow-700' : 'text-gray-900' }}">{{ $flaggedLogs }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ __('Unknown biometric IDs or wrong device time') }}</p>
            </a>
            <a href="{{ route('attendance.summary') }}" class="block rounded-lg border border-gray-200 p-4 hover:border-brand-300">
                <p class="text-sm font-medium text-gray-500">{{ __('Missing punches this month') }}</p>
                <p class="mt-2 text-2xl font-semibold {{ $missingPunchDays ? 'text-yellow-700' : 'text-gray-900' }}">{{ $missingPunchDays }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ __('Correct these before validating DTRs') }}</p>
            </a>
        </div>
    </x-card>

    <x-card :title="__('Leave')">
        <x-slot name="actions">
            <x-button-link :href="route('leave.credits')" variant="secondary">{{ __('Leave Credits') }}</x-button-link>
        </x-slot>

        <div class="grid gap-4 sm:grid-cols-2">
            <a href="{{ route('leave.requests.index') }}" class="block rounded-lg border border-gray-200 p-4 hover:border-brand-300">
                <p class="text-sm font-medium text-gray-500">{{ __('Applications awaiting approval') }}</p>
                <p class="mt-2 text-2xl font-semibold {{ $pendingApprovals ? 'text-yellow-700' : 'text-gray-900' }}">{{ $pendingApprovals }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ __('Endorsed by the department head or the Mayor') }}</p>
            </a>
            <a href="{{ route('leave.posting.index') }}" class="block rounded-lg border border-gray-200 p-4 hover:border-brand-300">
                <p class="text-sm font-medium text-gray-500">{{ __('Leave credits not yet posted for :month', ['month' => now()->subMonthNoOverflow()->format('F')]) }}</p>
                <p class="mt-2 text-2xl font-semibold {{ $unpostedLastMonth ? 'text-yellow-700' : 'text-gray-900' }}">{{ $unpostedLastMonth }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ __('Validate their DTRs to post the credits') }}</p>
            </a>
        </div>
    </x-card>

    @include('dashboard.partials.my-attendance')

    @include('dashboard.partials.my-leave')

    <div class="grid gap-4 sm:grid-cols-2">
        <x-upcoming-module :title="__('Payroll status')" icon="banknotes">
            {{ __('Current payroll run and its status (Phase 5).') }}
        </x-upcoming-module>
        <x-upcoming-module :title="__('Announcements')" icon="megaphone">
            {{ __('Post announcements to all employees (Phase 6).') }}
        </x-upcoming-module>
    </div>

    <x-card :title="__('Reference data')" :description="__('Keep offices, positions and employment types up to date before encoding employee records.')">
        <div class="flex flex-wrap gap-2">
            <x-button-link :href="route('reference-data.departments.index')" variant="secondary">{{ __('Departments') }}</x-button-link>
            <x-button-link :href="route('reference-data.positions.index')" variant="secondary">{{ __('Positions') }}</x-button-link>
            <x-button-link :href="route('reference-data.employment-types.index')" variant="secondary">{{ __('Employment Types') }}</x-button-link>
        </div>
    </x-card>
</x-app-layout>
