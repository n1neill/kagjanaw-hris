<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceLogStatus;
use App\Enums\AuditEvent;
use App\Enums\DtrStatus;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveLedgerEntryType;
use App\Enums\LeaveRequestStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AttendanceLog;
use App\Models\AuditLog;
use App\Models\BiometricDevice;
use App\Models\DailyTimeRecord;
use App\Models\Department;
use App\Models\DeviceSyncRun;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Backup\BackupService;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveCreditPostingService;
use App\Services\Leave\LeaveEndorsementRouter;
use App\Services\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Renders the dashboard that matches the signed-in user's primary role.
 */
class DashboardController extends Controller
{
    public function __construct(
        private LeaveBalanceService $leaveBalances,
        private LeaveEndorsementRouter $endorsements,
        private LeaveCreditPostingService $leavePosting,
        private BackupService $backups,
        private SystemSettings $settings,
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $role = $user->primaryRole();

        abort_if($role === null, 403, __('Your account has no role assigned. Please contact the System Administrator.'));

        $announcements = Announcement::query()->visibleTo($user)->latestFirst()->limit(3)->get();

        return view($role->dashboardView(), ['announcements' => $announcements, ...match ($role) {
            Role::SystemAdministrator => $this->administratorData(),
            Role::HrOfficer => [...$this->hrData(), ...$this->ownAttendanceData($user), ...$this->ownLeaveData($user), ...$this->ownPayslipData($user)],
            Role::MunicipalMayor, Role::DepartmentHead => [
                ...$this->departmentHeadData($user),
                ...$this->ownAttendanceData($user),
                ...$this->ownLeaveData($user),
                ...$this->ownPayslipData($user),
                'pendingEndorsements' => $this->endorsements->awaitingEndorsementBy($user)->count(),
            ],
            Role::Employee => [...$this->employeeData($user), ...$this->ownAttendanceData($user), ...$this->ownLeaveData($user), ...$this->ownPayslipData($user)],
        }]);
    }

    /**
     * The signed-in employee's leave balances and open applications.
     *
     * @return array{myLeave: ?array<string, mixed>, myOpenLeaveRequests: int}
     */
    private function ownLeaveData(User $user): array
    {
        $employee = $user->employee;

        return [
            'myLeave' => $employee === null ? null : $this->leaveBalances->summary($employee),
            'myOpenLeaveRequests' => $employee === null ? 0 : $employee->leaveRequests()
                ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Endorsed])
                ->count(),
        ];
    }

    /**
     * The signed-in employee's latest released payslip.
     *
     * @return array{myLatestPayslip: ?PayrollItem}
     */
    private function ownPayslipData(User $user): array
    {
        return [
            'myLatestPayslip' => $user->employee?->payrollItems()
                ->with('run.period')
                ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_items.payroll_run_id')
                ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.payroll_period_id')
                ->where('payroll_runs.status', PayrollRunStatus::Released)
                ->orderByDesc('payroll_periods.start_date')
                ->orderByDesc('payroll_items.id')
                ->select('payroll_items.*')
                ->first(),
        ];
    }

    /**
     * Today's punches and this month's tardiness and absences for the signed-in employee.
     *
     * @return array{myToday: ?DailyTimeRecord, myMonth: ?array{late_count: int, late_minutes: int, absent_days: float}}
     */
    private function ownAttendanceData(User $user): array
    {
        $employee = $user->employee;

        if ($employee === null) {
            return ['myToday' => null, 'myMonth' => null];
        }

        $monthRecords = $employee->dailyTimeRecords()
            ->whereBetween('work_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->get();

        $rules = WorkSchedule::current()->rules();

        return [
            'myToday' => $monthRecords->first(fn (DailyTimeRecord $record): bool => $record->work_date->isToday()),
            'myMonth' => [
                'late_count' => $monthRecords->filter(fn (DailyTimeRecord $record): bool => $record->late_minutes > 0)->count(),
                'late_minutes' => (int) $monthRecords->sum('late_minutes'),
                'absent_days' => round($monthRecords->sum('absent_minutes') / ($rules->amLength() + $rules->pmLength()), 1),
            ],
        ];
    }

    /**
     * @return array{totalUsers: int, activeUsers: int, inactiveUsers: int, usersPerRole: array<string, int>}
     */
    private function administratorData(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::active()->count();

        $countsByRoleName = RoleModel::query()->withCount('users')->pluck('users_count', 'name');

        $usersPerRole = [];

        foreach (Role::cases() as $role) {
            $usersPerRole[$role->label()] = (int) $countsByRoleName->get($role->value, 0);
        }

        return [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'inactiveUsers' => $totalUsers - $activeUsers,
            'usersPerRole' => $usersPerRole,
            'devices' => BiometricDevice::query()->orderBy('name')->get(),
            'latestBackup' => $this->backups->all()->first(),
            'backupEnabled' => $this->settings->isBackupEnabled(),
            'backupTime' => $this->settings->backupTime(),
            'failedSignIns' => AuditLog::query()
                ->whereIn('event', [AuditEvent::LoginFailed, AuditEvent::LockedOut])
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'deniedRequests' => AuditLog::query()
                ->where('event', AuditEvent::AccessDenied)
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];
    }

    /**
     * @return array{employeesInService: int, separatedEmployees: int, latestSync: ?DeviceSyncRun, flaggedLogs: int, missingPunchDays: int}
     */
    private function hrData(): array
    {
        $countsByStatus = Employee::query()
            ->selectRaw('employment_status, count(*) as total')
            ->groupBy('employment_status')
            ->pluck('total', 'employment_status');

        $employeesInService = (int) $countsByStatus->get(EmploymentStatus::Active->value, 0);

        return [
            'employeesInService' => $employeesInService,
            'separatedEmployees' => (int) $countsByStatus->sum() - $employeesInService,
            'latestSync' => DeviceSyncRun::query()->with('device')->latest('started_at')->latest('id')->first(),
            'flaggedLogs' => AttendanceLog::query()->where('status', '!=', AttendanceLogStatus::Valid)->count(),
            'missingPunchDays' => DailyTimeRecord::query()
                ->where('status', DtrStatus::Incomplete)
                ->whereBetween('work_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
                ->count(),
            'pendingApprovals' => LeaveRequest::query()->where('status', LeaveRequestStatus::Endorsed)->count(),
            'unpostedLastMonth' => $this->unpostedLeaveCredits(),
            'recentPayrollRuns' => PayrollRun::query()
                ->with('period')
                ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.payroll_period_id')
                ->orderByDesc('payroll_periods.start_date')
                ->orderBy('payroll_runs.pay_basis')
                ->select('payroll_runs.*')
                ->limit(4)
                ->get(),
        ];
    }

    /**
     * Employees whose leave credits for last month are not yet posted.
     */
    private function unpostedLeaveCredits(): int
    {
        $month = now()->subMonthNoOverflow()->startOfMonth()->toImmutable();
        $employees = $this->leavePosting->employeesFor($month);

        $posted = LeaveLedgerEntry::query()
            ->where('entry_type', LeaveLedgerEntryType::Accrual)
            ->whereDate('period', $month->toDateString())
            ->distinct()
            ->count('employee_id');

        return max(0, $employees->count() - $posted);
    }

    /**
     * @return array{department: ?Department, teamSize: int, reportedToday: int, lateToday: int}
     */
    private function departmentHeadData(User $user): array
    {
        $department = $user->employee?->department;

        if ($department === null) {
            return ['department' => null, 'teamSize' => 0, 'reportedToday' => 0, 'lateToday' => 0];
        }

        $today = DailyTimeRecord::query()
            ->whereDate('work_date', now()->toDateString())
            ->whereHas('employee', fn ($query) => $query->where('department_id', $department->id))
            ->get();

        return [
            'department' => $department,
            'teamSize' => Employee::query()->inService()->where('department_id', $department->id)->count(),
            'reportedToday' => $today->filter(fn (DailyTimeRecord $record): bool => $record->am_in !== null || $record->pm_in !== null)->count(),
            'lateToday' => $today->filter(fn (DailyTimeRecord $record): bool => $record->late_minutes > 0)->count(),
        ];
    }

    /**
     * @return array{employee: ?Employee}
     */
    private function employeeData(User $user): array
    {
        return [
            'employee' => $user->employee?->load(['department', 'position']),
        ];
    }
}
