<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BiometricDeviceActionController;
use App\Http\Controllers\Admin\BiometricDeviceController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Attendance\AttendanceImportController;
use App\Http\Controllers\Attendance\AttendanceLogController;
use App\Http\Controllers\Attendance\AttendanceSummaryController;
use App\Http\Controllers\Attendance\DtrController;
use App\Http\Controllers\Attendance\DtrCorrectionController;
use App\Http\Controllers\Attendance\DtrMonthController;
use App\Http\Controllers\Attendance\MyAttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Employees\EmployeeDocumentController;
use App\Http\Controllers\Employees\MyRecordController;
use App\Http\Controllers\Leave\LeaveAdjustmentController;
use App\Http\Controllers\Leave\LeaveCardController;
use App\Http\Controllers\Leave\LeaveCertificateController;
use App\Http\Controllers\Leave\LeaveCreditController;
use App\Http\Controllers\Leave\LeaveEndorsementController;
use App\Http\Controllers\Leave\LeavePostingController;
use App\Http\Controllers\Leave\LeaveRequestActionController;
use App\Http\Controllers\Leave\LeaveRequestController;
use App\Http\Controllers\Leave\LeaveTypeController;
use App\Http\Controllers\Leave\LeaveUtilizationController;
use App\Http\Controllers\Leave\MyLeaveController;
use App\Http\Controllers\Leave\OpeningBalanceImportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Payroll\AllowanceTypeController;
use App\Http\Controllers\Payroll\DeductionRateController;
use App\Http\Controllers\Payroll\EmployeeAllowanceController;
use App\Http\Controllers\Payroll\EmployeeDeductionController;
use App\Http\Controllers\Payroll\EmployeePayController;
use App\Http\Controllers\Payroll\MyPayslipController;
use App\Http\Controllers\Payroll\PayrollRegisterController;
use App\Http\Controllers\Payroll\PayrollRunActionController;
use App\Http\Controllers\Payroll\PayrollRunController;
use App\Http\Controllers\Payroll\PayrollSettingsController;
use App\Http\Controllers\Payroll\PayslipController;
use App\Http\Controllers\Payroll\SalaryScheduleController;
use App\Http\Controllers\Payroll\TaxTableController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferenceData\DepartmentController;
use App\Http\Controllers\ReferenceData\EmploymentTypeController;
use App\Http\Controllers\ReferenceData\PositionController;
use App\Http\Controllers\Reports\EmployeeListController;
use App\Http\Controllers\Reports\PayrollSummaryController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\WorkCalendar\HolidayController;
use App\Http\Controllers\WorkCalendar\WorkScheduleController;
use App\Services\Reports\ReportExporter;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');

    Route::get('/my-record', MyRecordController::class)
        ->middleware(PermissionMiddleware::using(Permission::ViewOwnRecords))
        ->name('my-record');

    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware(PermissionMiddleware::using([Permission::ManageEmployees, Permission::ViewDepartmentEmployees]))
        ->name('employees.index');

    Route::middleware(PermissionMiddleware::using(Permission::ManageEmployees))->group(function () {
        Route::resource('employees', EmployeeController::class)->only(['create', 'store', 'edit', 'update']);
        Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
        Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])
            ->scopeBindings()
            ->name('employees.documents.destroy');
    });

    // Reachable by HR, the employee's department head and the employee; the policy decides.
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'show'])
        ->scopeBindings()
        ->name('employees.documents.show');
    Route::get('employees/{employee}/documents/{document}/download', [EmployeeDocumentController::class, 'download'])
        ->scopeBindings()
        ->name('employees.documents.download');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{notification}', [NotificationController::class, 'open'])
        ->whereUuid('notification')
        ->name('notifications.open');

    // Everyone reads the announcements addressed to them; HR manages them. The policy decides.
    Route::resource('announcements', AnnouncementController::class);

    Route::prefix('leave')->name('leave.')->group(function () {
        Route::middleware(PermissionMiddleware::using(Permission::FileLeave))->group(function () {
            Route::get('/', MyLeaveController::class)->name('my');
            Route::get('apply', [LeaveRequestController::class, 'create'])->name('requests.create');
            Route::post('requests', [LeaveRequestController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('requests.store');
        });

        Route::get('requests', [LeaveRequestController::class, 'index'])
            ->middleware(PermissionMiddleware::using(Permission::ApproveLeave))
            ->name('requests.index');

        Route::get('endorsements', LeaveEndorsementController::class)
            ->middleware(PermissionMiddleware::using([Permission::EndorseLeave, Permission::EndorseDepartmentHeadLeave]))
            ->name('endorsements');

        // Reachable by the employee, their endorser and HR; the policy decides.
        Route::get('requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('requests.show');
        Route::get('requests/{leaveRequest}/attachment', [LeaveRequestController::class, 'attachment'])->name('requests.attachment');
        Route::post('requests/{leaveRequest}/endorse', [LeaveRequestActionController::class, 'endorse'])->name('requests.endorse');
        Route::post('requests/{leaveRequest}/approve', [LeaveRequestActionController::class, 'approve'])->name('requests.approve');
        Route::post('requests/{leaveRequest}/reject', [LeaveRequestActionController::class, 'reject'])->name('requests.reject');
        Route::post('requests/{leaveRequest}/cancel', [LeaveRequestActionController::class, 'cancel'])->name('requests.cancel');

        // HR and the employee themselves; the policy decides.
        Route::get('employees/{employee}/card', LeaveCardController::class)->name('card');

        Route::middleware(PermissionMiddleware::using(Permission::ManageLeaveCredits))->group(function () {
            Route::get('credits', LeaveCreditController::class)->name('credits');
            Route::post('employees/{employee}/adjustments', LeaveAdjustmentController::class)->name('adjustments.store');
            Route::get('employees/{employee}/certificate', LeaveCertificateController::class)->name('certificate');
            Route::get('posting', [LeavePostingController::class, 'index'])->name('posting.index');
            Route::post('posting', [LeavePostingController::class, 'store'])->name('posting.store');
            Route::get('opening-balances', [OpeningBalanceImportController::class, 'create'])->name('opening-balances.create');
            Route::post('opening-balances', [OpeningBalanceImportController::class, 'store'])->name('opening-balances.store');
            Route::get('opening-balances/template', [OpeningBalanceImportController::class, 'template'])->name('opening-balances.template');
            Route::resource('types', LeaveTypeController::class)->except(['show', 'destroy'])->parameters(['types' => 'leaveType']);
        });

        // HR sees every office; department heads their own (the request decides).
        Route::middleware(PermissionMiddleware::using([Permission::ManageLeaveCredits, Permission::GenerateReports, Permission::ViewDepartmentReports]))->group(function () {
            Route::get('reports/utilization', [LeaveUtilizationController::class, 'index'])->name('reports.utilization');
            Route::get('reports/utilization/export.{format}', [LeaveUtilizationController::class, 'export'])
                ->whereIn('format', ReportExporter::FORMATS)
                ->name('reports.utilization.export');
        });
    });

    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('my-payslips', MyPayslipController::class)
            ->middleware(PermissionMiddleware::using(Permission::ViewOwnPayslips))
            ->name('payslips.my');

        // HR and the employee themselves (released payslips only); the policy decides.
        Route::get('payslips/{payrollItem}', PayslipController::class)->name('payslips.show');

        Route::middleware(PermissionMiddleware::using(Permission::ProcessPayroll))->group(function () {
            Route::get('runs', [PayrollRunController::class, 'index'])->name('runs.index');
            Route::post('runs', [PayrollRunController::class, 'store'])->name('runs.store');
            Route::get('runs/{payrollRun}', [PayrollRunController::class, 'show'])->name('runs.show');
            Route::delete('runs/{payrollRun}', [PayrollRunController::class, 'destroy'])->name('runs.destroy');
            Route::post('runs/{payrollRun}/compute', [PayrollRunActionController::class, 'compute'])->name('runs.compute');
            Route::post('runs/{payrollRun}/approve', [PayrollRunActionController::class, 'approve'])->name('runs.approve');
            Route::post('runs/{payrollRun}/reopen', [PayrollRunActionController::class, 'reopen'])->name('runs.reopen');
            Route::post('runs/{payrollRun}/release', [PayrollRunActionController::class, 'release'])->name('runs.release');
            Route::get('runs/{payrollRun}/register.{format}', PayrollRegisterController::class)
                ->whereIn('format', ['xlsx', 'csv', 'pdf'])
                ->name('runs.register');

            Route::get('employees/{employee}', [EmployeePayController::class, 'show'])->name('employees.show');
            Route::post('employees/{employee}/allowances', [EmployeeAllowanceController::class, 'store'])->name('employees.allowances.store');
            Route::delete('employees/{employee}/allowances/{allowance}', [EmployeeAllowanceController::class, 'destroy'])
                ->scopeBindings()
                ->name('employees.allowances.destroy');
            Route::post('employees/{employee}/deductions', [EmployeeDeductionController::class, 'store'])->name('employees.deductions.store');
            Route::get('employees/{employee}/deductions/{payrollDeduction}/edit', [EmployeeDeductionController::class, 'edit'])
                ->scopeBindings()
                ->name('employees.deductions.edit');
            Route::put('employees/{employee}/deductions/{payrollDeduction}', [EmployeeDeductionController::class, 'update'])
                ->scopeBindings()
                ->name('employees.deductions.update');

            Route::resource('allowances', AllowanceTypeController::class)->except(['show', 'destroy'])->parameters(['allowances' => 'allowanceType']);

            Route::get('settings', [PayrollSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [PayrollSettingsController::class, 'update'])->name('settings.update');

            Route::get('salary-schedule', [SalaryScheduleController::class, 'index'])->name('salary-schedule.index');
            Route::post('salary-schedule', [SalaryScheduleController::class, 'store'])->name('salary-schedule.store');
            Route::get('salary-schedule/{effectiveFrom}/edit', [SalaryScheduleController::class, 'edit'])
                ->where('effectiveFrom', '\d{4}-\d{2}-\d{2}')
                ->name('salary-schedule.edit');
            Route::put('salary-schedule/{effectiveFrom}', [SalaryScheduleController::class, 'update'])
                ->where('effectiveFrom', '\d{4}-\d{2}-\d{2}')
                ->name('salary-schedule.update');

            Route::get('rates', [DeductionRateController::class, 'index'])->name('rates.index');
            Route::get('rates/contributions/create', [DeductionRateController::class, 'create'])->name('rates.contributions.create');
            Route::post('rates/contributions', [DeductionRateController::class, 'store'])->name('rates.contributions.store');
            Route::get('rates/contributions/{deductionRate}/edit', [DeductionRateController::class, 'edit'])->name('rates.contributions.edit');
            Route::put('rates/contributions/{deductionRate}', [DeductionRateController::class, 'update'])->name('rates.contributions.update');
            Route::get('rates/tax/{effectiveFrom}/edit', [TaxTableController::class, 'edit'])
                ->where('effectiveFrom', '\d{4}-\d{2}-\d{2}')
                ->name('rates.tax.edit');
            Route::put('rates/tax/{effectiveFrom}', [TaxTableController::class, 'update'])
                ->where('effectiveFrom', '\d{4}-\d{2}-\d{2}')
                ->name('rates.tax.update');
            Route::post('rates/tax', [TaxTableController::class, 'store'])->name('rates.tax.store');
        });
    });

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('my', MyAttendanceController::class)
            ->middleware(PermissionMiddleware::using(Permission::ViewOwnAttendance))
            ->name('my');

        Route::middleware(PermissionMiddleware::using([Permission::ManageAttendance, Permission::ViewDepartmentAttendance]))->group(function () {
            Route::get('summary', [AttendanceSummaryController::class, 'index'])->name('summary');
            Route::get('summary/export.{format}', [AttendanceSummaryController::class, 'export'])
                ->whereIn('format', ReportExporter::FORMATS)
                ->name('summary.export');
        });

        // Reachable by HR, the employee's department head and the employee; the policy decides.
        Route::get('employees/{employee}/dtr', [DtrController::class, 'show'])->name('dtr.show');
        Route::get('employees/{employee}/dtr/pdf', [DtrController::class, 'pdf'])->name('dtr.pdf');

        Route::middleware(PermissionMiddleware::using(Permission::ManageAttendance))->group(function () {
            Route::get('employees/{employee}/dtr/{date}/edit', [DtrCorrectionController::class, 'edit'])
                ->where('date', '\d{4}-\d{2}-\d{2}')
                ->name('dtr.edit');
            Route::put('employees/{employee}/dtr/{date}', [DtrCorrectionController::class, 'update'])
                ->where('date', '\d{4}-\d{2}-\d{2}')
                ->name('dtr.update');
            Route::post('employees/{employee}/dtr/validate', [DtrMonthController::class, 'validateMonth'])->name('dtr.validate');
            Route::post('employees/{employee}/dtr/reprocess', [DtrMonthController::class, 'reprocess'])->name('dtr.reprocess');

            Route::get('logs', [AttendanceLogController::class, 'index'])->name('logs');
            Route::get('import', [AttendanceImportController::class, 'create'])->name('import.create');
            Route::post('import', [AttendanceImportController::class, 'store'])->name('import.store');
            Route::get('import/template', [AttendanceImportController::class, 'template'])->name('import.template');
        });
    });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(PermissionMiddleware::using(Permission::ManageUsers))
        ->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::patch('users/{user}/status', [UserStatusController::class, 'update'])->name('users.status');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(PermissionMiddleware::using(Permission::ManageBiometricDevices))
        ->group(function () {
            Route::resource('devices', BiometricDeviceController::class)->except(['destroy']);
            Route::post('devices/{device}/test', [BiometricDeviceActionController::class, 'testConnection'])->name('devices.test');
            Route::post('devices/{device}/sync', [BiometricDeviceActionController::class, 'sync'])
                ->middleware('throttle:10,1')
                ->name('devices.sync');
        });

    Route::prefix('reports')->name('reports.')->group(function () {
        // HR sees every office; department heads their own (the request decides).
        Route::middleware(PermissionMiddleware::using([Permission::GenerateReports, Permission::ViewDepartmentReports]))->group(function () {
            Route::get('/', ReportController::class)->name('index');
            Route::get('employees', [EmployeeListController::class, 'index'])->name('employees');
            Route::get('employees/export.{format}', [EmployeeListController::class, 'export'])
                ->whereIn('format', ReportExporter::FORMATS)
                ->name('employees.export');
        });

        Route::middleware(PermissionMiddleware::using(Permission::ProcessPayroll))->group(function () {
            Route::get('payroll', [PayrollSummaryController::class, 'index'])->name('payroll');
            Route::get('payroll/export.{format}', [PayrollSummaryController::class, 'export'])
                ->whereIn('format', ReportExporter::FORMATS)
                ->name('payroll.export');
        });
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware(PermissionMiddleware::using(Permission::ViewAuditLogs))->group(function () {
            Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('audit-log/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
        });

        Route::middleware(PermissionMiddleware::using(Permission::ManageSettings))->group(function () {
            Route::get('settings', [SystemSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SystemSettingsController::class, 'update'])->name('settings.update');
        });

        Route::middleware(PermissionMiddleware::using(Permission::ManageBackups))->group(function () {
            Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
            Route::post('backups', [BackupController::class, 'store'])->middleware('throttle:5,1')->name('backups.store');
            Route::post('backups/upload', [BackupController::class, 'upload'])->name('backups.upload');
            Route::get('backups/{backup}', [BackupController::class, 'download'])->where('backup', '[A-Za-z0-9._-]+\.zip')->name('backups.download');
            Route::post('backups/{backup}/restore', [BackupController::class, 'restore'])
                ->where('backup', '[A-Za-z0-9._-]+\.zip')
                ->middleware('throttle:5,1')
                ->name('backups.restore');
            Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->where('backup', '[A-Za-z0-9._-]+\.zip')->name('backups.destroy');
        });
    });

    Route::prefix('work-calendar')
        ->name('work-calendar.')
        ->middleware(PermissionMiddleware::using(Permission::ManageWorkCalendar))
        ->group(function () {
            Route::get('office-hours', [WorkScheduleController::class, 'edit'])->name('schedule.edit');
            Route::put('office-hours', [WorkScheduleController::class, 'update'])->name('schedule.update');
            Route::resource('holidays', HolidayController::class)->except(['show']);
        });

    Route::prefix('reference-data')
        ->name('reference-data.')
        ->middleware(PermissionMiddleware::using(Permission::ManageReferenceData))
        ->group(function () {
            Route::resource('departments', DepartmentController::class)->except(['show', 'destroy']);
            Route::resource('positions', PositionController::class)->except(['show', 'destroy']);
            Route::resource('employment-types', EmploymentTypeController::class)->except(['show', 'destroy']);
        });
});

require __DIR__.'/auth.php';
