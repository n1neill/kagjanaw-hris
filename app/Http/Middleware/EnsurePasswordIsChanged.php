<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a user whose password was set by someone else (a new account or an
 * administrator reset) to the change-password page before anything else.
 */
class EnsurePasswordIsChanged
{
    /**
     * Routes that stay reachable while a password change is pending.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = ['password.change', 'password.change.update', 'logout'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->must_change_password && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
