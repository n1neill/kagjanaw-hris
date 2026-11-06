<?php

namespace App\Http\Controllers\Kiosk;

use App\Enums\Finger;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateKioskDevice;
use App\Http\Requests\Kiosk\EnrollFingerprintRequest;
use App\Models\Employee;
use App\Services\Attendance\Kiosk\FingerprintEnrollmentService;
use Illuminate\Http\JsonResponse;

/**
 * Save a finger HR enrolled at the kiosk (new, or replacing the same finger).
 */
class KioskFingerprintController extends Controller
{
    public function __invoke(EnrollFingerprintRequest $request, Employee $employee, Finger $finger, FingerprintEnrollmentService $enrollment): JsonResponse
    {
        $fingerprint = $enrollment->enroll(
            $employee,
            $finger,
            $request->validated('template'),
            $request->validated('quality'),
            $request->user(),
            AuthenticateKioskDevice::device($request),
        );

        return response()->json([
            'employee_id' => $employee->id,
            'finger' => $finger->value,
            'enrolled_fingers' => $employee->fingerprintTemplates()->pluck('finger')->map(fn (Finger $enrolled): string => $enrolled->value)->all(),
        ], $fingerprint->wasRecentlyCreated ? 201 : 200);
    }
}
