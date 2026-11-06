<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateKioskDevice;
use App\Http\Middleware\AuthenticateKioskEnrollment;
use App\Http\Requests\Kiosk\StartEnrollmentSessionRequest;
use App\Services\Attendance\Kiosk\KioskEnrollmentSessions;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * HR signing in and out at the kiosk to enroll fingerprints.
 */
class KioskEnrollmentSessionController extends Controller
{
    public function store(StartEnrollmentSessionRequest $request, AuditLogger $audit, KioskEnrollmentSessions $sessions): JsonResponse
    {
        $user = $request->authenticate($audit);
        $session = $sessions->start(AuthenticateKioskDevice::device($request), $user);

        return response()->json([
            'token' => $session['token'],
            'expires_at' => $session['expires_at']->toIso8601String(),
            'user' => ['id' => $user->id, 'name' => $user->name],
        ], 201);
    }

    public function destroy(Request $request, KioskEnrollmentSessions $sessions): Response
    {
        $sessions->end((string) $request->header(AuthenticateKioskEnrollment::TOKEN_HEADER));

        return response()->noContent();
    }
}
