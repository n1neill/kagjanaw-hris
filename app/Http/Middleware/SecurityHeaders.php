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
        if (str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
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
        $devServer = $this->viteDevServer();

        return implode('; ', [
            "default-src 'self'",
            trim("script-src 'self' 'unsafe-eval' {$devServer['http']}"),
            trim("style-src 'self' 'unsafe-inline' {$devServer['http']}"),
            "img-src 'self' data:",
            "font-src 'self' data:",
            trim("connect-src 'self' {$devServer['http']} {$devServer['ws']}"),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * While `npm run dev` runs, assets and hot reloads come from Vite's server.
     *
     * @return array{http: string, ws: string}
     */
    private function viteDevServer(): array
    {
        if (! Vite::isRunningHot()) {
            return ['http' => '', 'ws' => ''];
        }

        $origin = rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');

        return ['http' => $origin, 'ws' => preg_replace('/^http/', 'ws', $origin)];
    }
}
