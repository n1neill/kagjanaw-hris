<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers on every response: no framing (clickjacking),
 * no MIME sniffing, no referrer sent to other sites, and on HTML pages a
 * Content Security Policy that only runs the application's own bundled scripts.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // PDFs and downloads open in the browser's own viewer, which a page policy can break.
        // While `npm run dev` runs, assets come from Vite's server, often at an IPv6
        // address ([::1]) that a policy cannot name, so development pages go without.
        if (str_starts_with((string) $response->headers->get('Content-Type'), 'text/html') && ! Vite::isRunningHot()) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        return $response;
    }

    /**
     * Alpine.js evaluates its attributes with `new Function()` ('unsafe-eval'),
     * and Alpine and DataTables set inline styles. Inline <script> blocks and
     * event handler attributes (onclick, onsubmit) are refused.
     */
    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
