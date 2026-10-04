<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\DashboardController;
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
