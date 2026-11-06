<?php

namespace App\Http\Middleware;

use App\Services\Attendance\Kiosk\KioskEnrollmentSessions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admits enrollment requests from a kiosk once an HR officer has signed in
 * there (X-Enrollment-Token header). The officer becomes the request's user,
 * so the audit log records them as the one who enrolled.
 */
class AuthenticateKioskEnrollment
{
    public const TOKEN_HEADER = 'X-Enrollment-Token';

    public function __construct(private KioskEnrollmentSessions $sessions) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->sessions->user(
            AuthenticateKioskDevice::device($request),
            (string) $request->header(self::TOKEN_HEADER),
        );

        abort_if($user === null, 401, __('Your HR sign-in at the kiosk has ended. Sign in again to enroll fingerprints.'));

        Auth::setUser($user);

        return $next($request);
    }
}
