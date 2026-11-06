<?php

use App\Http\Controllers\Kiosk\KioskEmployeeController;
use App\Http\Controllers\Kiosk\KioskEnrollmentSessionController;
use App\Http\Controllers\Kiosk\KioskFingerprintController;
use App\Http\Controllers\Kiosk\KioskHeartbeatController;
use App\Http\Controllers\Kiosk\KioskPunchController;
use App\Http\Controllers\Kiosk\KioskTemplateController;
use App\Http\Middleware\AuthenticateKioskEnrollment;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fingerprint Kiosk API (/api/kiosk/v1)
|--------------------------------------------------------------------------
|
| Used by the DigitalPersona kiosk app (kiosk/ in this repository). Every
| request carries the kiosk's token; enrollment requests also carry the
| session token of the HR officer signed in at the kiosk.
|
*/

Route::get('heartbeat', KioskHeartbeatController::class)->name('heartbeat');
Route::get('templates', KioskTemplateController::class)
    ->middleware('cache.headers:private;etag')
    ->name('templates');
Route::post('punches', KioskPunchController::class)->name('punches');

Route::post('enrollment-sessions', [KioskEnrollmentSessionController::class, 'store'])->name('enrollment-sessions.store');

Route::middleware(AuthenticateKioskEnrollment::class)->group(function () {
    Route::delete('enrollment-sessions/current', [KioskEnrollmentSessionController::class, 'destroy'])->name('enrollment-sessions.destroy');
    Route::get('employees', KioskEmployeeController::class)->name('employees');
    Route::put('employees/{employee}/fingerprints/{finger}', KioskFingerprintController::class)->name('fingerprints.update');
});
