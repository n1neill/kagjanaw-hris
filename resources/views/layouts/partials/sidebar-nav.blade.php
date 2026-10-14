@use('App\Enums\Permission')

{{-- Menu items are shown per permission; routes enforce the same permissions server-side. --}}
<nav class="flex flex-1 flex-col gap-6 overflow-y-auto px-3 py-5" aria-label="{{ __('Main') }}">
    <div class="space-y-1">
        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">
            {{ __('Dashboard') }}
        </x-sidebar-link>
        @can(Permission::ViewOwnRecords)
            <x-sidebar-link :href="route('my-record')" :active="request()->routeIs('my-record')" icon="identification">
                {{ __('My Record') }}
            </x-sidebar-link>
        @endcan
        @can(Permission::ViewOwnAttendance)
            <x-sidebar-link :href="route('attendance.my')" :active="request()->routeIs('attendance.my')" icon="clock">
                {{ __('My Attendance') }}
            </x-sidebar-link>
        @endcan
        @can(Permission::FileLeave)
            <x-sidebar-link :href="route('leave.my')" :active="request()->routeIs('leave.my', 'leave.requests.create')" icon="calendar">
                {{ __('My Leave') }}
            </x-sidebar-link>
        @endcan
        @can(Permission::ViewOwnPayslips)
            <x-sidebar-link :href="route('payroll.payslips.my')" :active="request()->routeIs('payroll.payslips.my')" icon="banknotes">
                {{ __('My Payslips') }}
            </x-sidebar-link>
        @endcan
        @canany([Permission::EndorseLeave, Permission::EndorseDepartmentHeadLeave])
            <x-sidebar-link :href="route('leave.endorsements')" :active="request()->routeIs('leave.endorsements')" icon="inbox">
                {{ __('Leave Endorsements') }}
            </x-sidebar-link>
        @endcanany
        <x-sidebar-link :href="route('announcements.index')" :active="request()->routeIs('announcements.*')" icon="megaphone">
            {{ __('Announcements') }}
        </x-sidebar-link>
        @canany([Permission::GenerateReports, Permission::ViewDepartmentReports])
            <x-sidebar-link :href="route('reports.index')" :active="request()->routeIs('reports.*')" icon="chart">
                {{ __('Reports') }}
            </x-sidebar-link>
        @endcanany
    </div>

    @can(Permission::ManageEmployees)
        <div>
            <p class="px-3 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle">{{ __('Human Resources') }}</p>
            <div class="mt-2 space-y-1">
                <x-sidebar-link :href="route('employees.index')" :active="request()->routeIs('employees.*')" icon="users">
                    {{ __('Employees') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('attendance.summary')" :active="request()->routeIs('attendance.summary', 'attendance.dtr.*')" icon="calendar">
                    {{ __('Attendance Summary') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('attendance.logs')" :active="request()->routeIs('attendance.logs')" icon="clock">
                    {{ __('Attendance Logs') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('attendance.import.create')" :active="request()->routeIs('attendance.import.*')" icon="document">
                    {{ __('Import Attendance') }}
                </x-sidebar-link>
            </div>
        </div>
    @elsecan(Permission::ViewDepartmentEmployees)
        <div>
            <p class="px-3 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle">{{ __('Department') }}</p>
            <div class="mt-2 space-y-1">
                <x-sidebar-link :href="route('employees.index')" :active="request()->routeIs('employees.*')" icon="users">
                    {{ __('My Department') }}
                </x-sidebar-link>
                @can(Permission::ViewDepartmentAttendance)
                    <x-sidebar-link :href="route('attendance.summary')" :active="request()->routeIs('attendance.summary', 'attendance.dtr.*')" icon="calendar">
                        {{ __('Department Attendance') }}
                    </x-sidebar-link>
                @endcan
            </div>
        </div>
    @endcan

    @canany([Permission::ApproveLeave, Permission::ManageLeaveCredits])
        <div>
            <p class="px-3 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle">{{ __('Leave') }}</p>
            <div class="mt-2 space-y-1">
                @can(Permission::ApproveLeave)
                    <x-sidebar-link :href="route('leave.requests.index')" :active="request()->routeIs('leave.requests.index', 'leave.requests.show')" icon="inbox">
                        {{ __('Leave Applications') }}
                    </x-sidebar-link>
                @endcan
                @can(Permission::ManageLeaveCredits)
                    <x-sidebar-link :href="route('leave.credits')" :active="request()->routeIs('leave.credits', 'leave.card', 'leave.opening-balances.*')" icon="book">
                        {{ __('Leave Credits') }}
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('leave.posting.index')" :active="request()->routeIs('leave.posting.*')" icon="check">
                        {{ __('Monthly Posting') }}
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('leave.reports.utilization')" :active="request()->routeIs('leave.reports.*')" icon="chart">
                        {{ __('Leave Utilization') }}
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('leave.types.index')" :active="request()->routeIs('leave.types.*')" icon="tag">
                        {{ __('Leave Types') }}
                    </x-sidebar-link>
                @endcan
            </div>
        </div>
    @endcanany

    @can(Permission::ProcessPayroll)
        <div>
            <p class="px-3 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle">{{ __('Payroll') }}</p>
            <div class="mt-2 space-y-1">
                <x-sidebar-link :href="route('payroll.runs.index')" :active="request()->routeIs('payroll.runs.*', 'payroll.employees.*')" icon="banknotes">
                    {{ __('Payroll Runs') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('payroll.allowances.index')" :active="request()->routeIs('payroll.allowances.*')" icon="tag">
                    {{ __('Allowances') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('payroll.salary-schedule.index')" :active="request()->routeIs('payroll.salary-schedule.*')" icon="chart">
                    {{ __('Salary Schedule') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('payroll.rates.index')" :active="request()->routeIs('payroll.rates.*')" icon="book">
                    {{ __('Contributions & Tax') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('payroll.settings.edit')" :active="request()->routeIs('payroll.settings.*')" icon="key">
                    {{ __('Payroll Settings') }}
                </x-sidebar-link>
            </div>
        </div>
    @endcan

    @can(Permission::ManageWorkCalendar)
        <div>
            <p class="px-3 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle">{{ __('Work Calendar') }}</p>
            <div class="mt-2 space-y-1">
                <x-sidebar-link :href="route('work-calendar.schedule.edit')" :active="request()->routeIs('work-calendar.schedule.*')" icon="clock">
                    {{ __('Office Hours') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('work-calendar.holidays.index')" :active="request()->routeIs('work-calendar.holidays.*')" icon="calendar">
                    {{ __('Holidays') }}
                </x-sidebar-link>
            </div>
        </div>
    @endcan

    @can(Permission::ManageReferenceData)
        <div>
            <p class="px-3 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle">{{ __('Reference Data') }}</p>
            <div class="mt-2 space-y-1">
                <x-sidebar-link :href="route('reference-data.departments.index')" :active="request()->routeIs('reference-data.departments.*')" icon="building">
                    {{ __('Departments') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('reference-data.positions.index')" :active="request()->routeIs('reference-data.positions.*')" icon="briefcase">
                    {{ __('Positions') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('reference-data.employment-types.index')" :active="request()->routeIs('reference-data.employment-types.*')" icon="tag">
                    {{ __('Employment Types') }}
                </x-sidebar-link>
            </div>
        </div>
    @endcan

    @canany([Permission::ManageUsers, Permission::ManageBiometricDevices, Permission::ManageSettings, Permission::ManageBackups, Permission::ViewAuditLogs])
        <div>
            <p class="px-3 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle">{{ __('Administration') }}</p>
            <div class="mt-2 space-y-1">
                @can(Permission::ManageUsers)
                    <x-sidebar-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="users">
                        {{ __('User Accounts') }}
                    </x-sidebar-link>
                @endcan
                @can(Permission::ManageBiometricDevices)
                    <x-sidebar-link :href="route('admin.devices.index')" :active="request()->routeIs('admin.devices.*')" icon="server">
                        {{ __('Biometric Devices') }}
                    </x-sidebar-link>
                @endcan
                @can(Permission::ManageBackups)
                    <x-sidebar-link :href="route('admin.backups.index')" :active="request()->routeIs('admin.backups.*')" icon="archive">
                        {{ __('Backups') }}
                    </x-sidebar-link>
                @endcan
                @can(Permission::ViewAuditLogs)
                    <x-sidebar-link :href="route('admin.audit-logs.index')" :active="request()->routeIs('admin.audit-logs.*')" icon="shield">
                        {{ __('Audit Log') }}
                    </x-sidebar-link>
                @endcan
                @can(Permission::ManageSettings)
                    <x-sidebar-link :href="route('admin.settings.edit')" :active="request()->routeIs('admin.settings.*')" icon="cog">
                        {{ __('System Settings') }}
                    </x-sidebar-link>
                @endcan
            </div>
        </div>
    @endcanany

    <div class="mt-auto space-y-1 border-t border-line pt-4">
        <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')" icon="user">
            {{ __('My Account') }}
        </x-sidebar-link>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-ink-muted transition hover:bg-red-50 hover:text-red-700 focus-visible:outline-2 focus-visible:outline-brand-500 dark:hover:bg-red-500/10 dark:hover:text-red-300">
                <x-icon name="logout" class="size-5 shrink-0 text-ink-subtle group-hover:text-current" />
                <span>{{ __('Log Out') }}</span>
            </button>
        </form>
    </div>
</nav>
