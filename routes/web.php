<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserStatusController;
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

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(PermissionMiddleware::using(Permission::ManageUsers))
        ->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::patch('users/{user}/status', [UserStatusController::class, 'update'])->name('users.status');
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
