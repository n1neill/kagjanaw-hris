<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('HR Dashboard') }}</x-slot>

    <p class="text-sm text-ink-muted">{{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}</p>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Employees in service')" :value="$employeesInService" icon="users" />
        <x-stat-card :label="__('Separated employees')" :value="$separatedEmployees" />
        <div class="flex items-center justify-center rounded-xl border border-line bg-surface p-4 shadow-xs">
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
            <div class="rounded-lg border border-line p-4">
                <p class="text-sm font-medium text-ink-muted">{{ __('Last device sync') }}</p>
                @if ($latestSync)
                    <p class="mt-2 text-sm text-ink">{{ $latestSync->device->name }}</p>
                    <p class="mt-1 text-sm text-ink-muted">
                        {{ $latestSync->started_at->diffForHumans() }}
                        <x-badge :color="$latestSync->status->badgeColor()">{{ $latestSync->status->label() }}</x-badge>
                    </p>
                @else
                    <p class="mt-2 text-sm text-ink-muted">{{ __('No device has been synchronized yet.') }}</p>
                @endif
            </div>
            <a href="{{ route('attendance.logs', ['status' => 'unknown-user']) }}" class="block rounded-lg border border-line p-4 hover:border-brand-300 dark:hover:border-brand-700">
                <p class="text-sm font-medium text-ink-muted">{{ __('Flagged punches') }}</p>
                <p class="mt-2 text-2xl font-semibold {{ $flaggedLogs ? 'text-yellow-700 dark:text-yellow-300' : 'text-ink' }}">{{ $flaggedLogs }}</p>
                <p class="mt-1 text-xs text-ink-muted">{{ __('Unknown biometric IDs or wrong device time') }}</p>
            </a>
            <a href="{{ route('attendance.summary') }}" class="block rounded-lg border border-line p-4 hover:border-brand-300 dark:hover:border-brand-700">
                <p class="text-sm font-medium text-ink-muted">{{ __('Missing punches this month') }}</p>
                <p class="mt-2 text-2xl font-semibold {{ $missingPunchDays ? 'text-yellow-700 dark:text-yellow-300' : 'text-ink' }}">{{ $missingPunchDays }}</p>
                <p class="mt-1 text-xs text-ink-muted">{{ __('Correct these before validating DTRs') }}</p>
            </a>
        </div>
    </x-card>

    <x-card :title="__('Leave')">
        <x-slot name="actions">
            <x-button-link :href="route('leave.credits')" variant="secondary">{{ __('Leave Credits') }}</x-button-link>
        </x-slot>

        <div class="grid gap-4 sm:grid-cols-2">
            <a href="{{ route('leave.requests.index') }}" class="block rounded-lg border border-line p-4 hover:border-brand-300 dark:hover:border-brand-700">
                <p class="text-sm font-medium text-ink-muted">{{ __('Applications awaiting approval') }}</p>
                <p class="mt-2 text-2xl font-semibold {{ $pendingApprovals ? 'text-yellow-700 dark:text-yellow-300' : 'text-ink' }}">{{ $pendingApprovals }}</p>
                <p class="mt-1 text-xs text-ink-muted">{{ __('Endorsed by the department head or the Mayor') }}</p>
            </a>
            <a href="{{ route('leave.posting.index') }}" class="block rounded-lg border border-line p-4 hover:border-brand-300 dark:hover:border-brand-700">
                <p class="text-sm font-medium text-ink-muted">{{ __('Leave credits not yet posted for :month', ['month' => now()->subMonthNoOverflow()->format('F')]) }}</p>
                <p class="mt-2 text-2xl font-semibold {{ $unpostedLastMonth ? 'text-yellow-700 dark:text-yellow-300' : 'text-ink' }}">{{ $unpostedLastMonth }}</p>
                <p class="mt-1 text-xs text-ink-muted">{{ __('Validate their DTRs to post the credits') }}</p>
            </a>
        </div>
    </x-card>

    @include('dashboard.partials.my-attendance')

    @include('dashboard.partials.my-leave')

    <x-card :title="__('Payroll status')">
        <x-slot name="actions">
            <x-button-link :href="route('payroll.runs.index')" variant="secondary">{{ __('Payroll Runs') }}</x-button-link>
        </x-slot>

        @if ($recentPayrollRuns->isEmpty())
            <p class="text-sm text-ink-muted">{{ __('No payroll has been created yet.') }}</p>
        @else
            <ul class="divide-y divide-line text-sm">
                @foreach ($recentPayrollRuns as $run)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a href="{{ route('payroll.runs.show', $run) }}" class="font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ $run->title() }}</a>
                        <span class="flex items-center gap-3">
                            <span class="tabular-nums text-ink-muted">{{ trans_choice('{0} no employees|{1} :count employee|[2,*] :count employees', $run->employee_count, ['count' => $run->employee_count]) }}</span>
                            <x-badge :color="$run->status->badgeColor()">{{ $run->status->label() }}</x-badge>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <div class="grid gap-4 sm:grid-cols-2">
        @include('dashboard.partials.my-payslip')
        @include('dashboard.partials.announcements')
    </div>

    <x-card :title="__('Reference data')" :description="__('Keep offices, positions and employment types up to date before encoding employee records.')">
        <div class="flex flex-wrap gap-2">
            <x-button-link :href="route('reference-data.departments.index')" variant="secondary">{{ __('Departments') }}</x-button-link>
            <x-button-link :href="route('reference-data.positions.index')" variant="secondary">{{ __('Positions') }}</x-button-link>
            <x-button-link :href="route('reference-data.employment-types.index')" variant="secondary">{{ __('Employment Types') }}</x-button-link>
        </div>
    </x-card>
</x-app-layout>
