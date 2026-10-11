<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\BiometricDeviceActionController;
use App\Http\Controllers\Admin\BiometricDeviceController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\Admin\WorkScheduleController;
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
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferenceData\DepartmentController;
use App\Http\Controllers\ReferenceData\EmploymentTypeController;
use App\Http\Controllers\ReferenceData\PositionController;
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

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(PermissionMiddleware::using(Permission::ManageSettings))
        ->group(function () {
            Route::get('work-schedule', [WorkScheduleController::class, 'edit'])->name('work-schedule.edit');
            Route::put('work-schedule', [WorkScheduleController::class, 'update'])->name('work-schedule.update');
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
