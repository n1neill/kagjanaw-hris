<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\BiometricDeviceActionController;
use App\Http\Controllers\Admin\BiometricDeviceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserStatusController;
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
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferenceData\DepartmentController;
use App\Http\Controllers\ReferenceData\EmploymentTypeController;
use App\Http\Controllers\ReferenceData\PositionController;
use App\Http\Controllers\WorkCalendar\HolidayController;
use App\Http\Controllers\WorkCalendar\WorkScheduleController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

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

    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{notification}', [NotificationController::class, 'open'])
        ->whereUuid('notification')
        ->name('notifications.open');

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

        Route::middleware(PermissionMiddleware::using([Permission::ManageLeaveCredits, Permission::GenerateReports]))->group(function () {
            Route::get('reports/utilization', [LeaveUtilizationController::class, 'index'])->name('reports.utilization');
            Route::get('reports/utilization/export', [LeaveUtilizationController::class, 'export'])->name('reports.utilization.export');
        });
    });

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('my', MyAttendanceController::class)
            ->middleware(PermissionMiddleware::using(Permission::ViewOwnAttendance))
            ->name('my');

        Route::middleware(PermissionMiddleware::using([Permission::ManageAttendance, Permission::ViewDepartmentAttendance]))->group(function () {
            Route::get('summary', [AttendanceSummaryController::class, 'index'])->name('summary');
            Route::get('summary/export', [AttendanceSummaryController::class, 'export'])->name('summary.export');
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
