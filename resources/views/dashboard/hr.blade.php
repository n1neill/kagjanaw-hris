<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('HR Dashboard') }}</x-slot>

    <x-page-header :title="__('Welcome back, :name.', ['name' => Auth::user()->name])" :description="now()->format('l, F j, Y')">
        <x-slot name="actions">
            <x-button-link :href="route('attendance.summary')" variant="secondary">{{ __('Attendance Summary') }}</x-button-link>
            <x-button-link :href="route('employees.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('New Employee') }}
            </x-button-link>
        </x-slot>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('Employees in service')" :value="number_format($employeesInService)" icon="users" :progress="$employeesInService + $separatedEmployees > 0 ? $employeesInService / ($employeesInService + $separatedEmployees) * 100 : 0" :trend="__(':count separated', ['count' => number_format($separatedEmployees)])" />
        <x-stat-card :label="__('Separated employees')" :value="number_format($separatedEmployees)" icon="briefcase" />
        <x-stat-card :label="__('Applications awaiting approval')" :value="$pendingApprovals" icon="inbox" :hint="__('Endorsed by the department head or the Mayor')" />
        <x-stat-card :label="__('Missing punches this month')" :value="$missingPunchDays" icon="clock" :hint="__('Correct these before validating DTRs')" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card :title="__('Attendance this month')" :description="__('Employees on time, late, on leave and absent on each working day so far.')" class="lg:col-span-2">
            <x-chart :data="$attendanceChart" :label="__('Attendance this month by day')" :empty="__('Attendance appears here once the DTRs for this month are processed.')" />
        </x-card>

        <x-card :title="__('Employees by office')" :description="__('Employees in service in each office.')">
            <x-chart :data="$headcountChart" :label="__('Employees in service by office')" height="h-72" />
        </x-card>

        <x-card :title="__('Leave taken this year')" :description="__('Days of approved leave by leave type, :year.', ['year' => now()->year])">
            <x-chart :data="$leaveChart" :label="__('Days of approved leave by leave type')" height="h-72" :empty="__('No leave has been approved this year yet.')" />
        </x-card>

        <x-card :title="__('Payroll totals')" :description="__('Gross and net pay of the last six pay periods with an approved or released payroll.')" class="lg:col-span-2">
            <x-slot name="actions">
                <x-button-link :href="route('payroll.runs.index')" variant="secondary">{{ __('Payroll Runs') }}</x-button-link>
            </x-slot>
            <x-chart :data="$payrollChart" :label="__('Gross and net pay by pay period')" :empty="__('Totals appear once a payroll is approved.')" />
        </x-card>
    </div>

    <x-card :title="__('Needs attention')" :description="__('Work waiting on the HR Office.')">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-line bg-surface p-4 shadow-xs">
                <p class="text-sm font-medium text-ink-muted">{{ __('Last device sync') }}</p>
                @if ($latestSync)
                    <p class="mt-2 text-sm font-medium text-ink">{{ $latestSync->device->name }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-ink-muted">
                        {{ $latestSync->started_at->diffForHumans() }}
                        <x-badge :color="$latestSync->status->badgeColor()">{{ $latestSync->status->label() }}</x-badge>
                    </p>
                @else
                    <p class="mt-2 text-sm text-ink-muted">{{ __('No device has been synchronized yet.') }}</p>
                @endif
            </div>
            @foreach ([
                ['href' => route('attendance.logs', ['status' => 'unknown-user']), 'label' => __('Flagged punches'), 'value' => $flaggedLogs, 'hint' => __('Unknown biometric IDs or wrong device time')],
                ['href' => route('leave.requests.index'), 'label' => __('Leave applications to decide'), 'value' => $pendingApprovals, 'hint' => __('Approve or reject them')],
                ['href' => route('leave.posting.index'), 'label' => __('Leave credits not yet posted for :month', ['month' => now()->subMonthNoOverflow()->format('F')]), 'value' => $unpostedLastMonth, 'hint' => __('Validate their DTRs to post the credits')],
            ] as $item)
                <x-stat-card :href="$item['href']" :label="$item['label']" :value="$item['value']" :hint="$item['hint']" :alert="(bool) $item['value']" class="p-4" />
            @endforeach
        </div>
    </x-card>

    @include('dashboard.partials.my-attendance')

    @include('dashboard.partials.my-leave')

    <x-card :title="__('Payroll status')" :flush="$recentPayrollRuns->isNotEmpty()">
        <x-slot name="actions">
            <x-button-link :href="route('payroll.runs.index')" variant="secondary">{{ __('Payroll Runs') }}</x-button-link>
        </x-slot>

        @if ($recentPayrollRuns->isEmpty())
            <x-empty-state icon="banknotes" :title="__('No payroll has been created yet.')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($recentPayrollRuns as $run)
                    <x-list-row :href="route('payroll.runs.show', $run)">
                        {{ $run->title() }}
                        <x-slot name="trailing">
                            <span class="tabular-nums">{{ trans_choice('{0} no employees|{1} :count employee|[2,*] :count employees', $run->employee_count, ['count' => $run->employee_count]) }}</span>
                            <x-badge :color="$run->status->badgeColor()">{{ $run->status->label() }}</x-badge>
                        </x-slot>
                    </x-list-row>
                @endforeach
            </ul>
        @endif
    </x-card>

    <div class="grid gap-6 lg:grid-cols-2">
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
