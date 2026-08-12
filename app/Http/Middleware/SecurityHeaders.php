<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security response headers for every web response.
 *
 * The logic lives in a static apply() because responses generated from
 * EXCEPTIONS — a 404, or an auth redirect — are rendered by the exception
 * handler and never bubble back out through this middleware.
 * bootstrap/app.php calls the same method from withExceptions()->respond()
 * so those responses are covered too.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($next($request), $request);
    }

    public static function apply(Response $response, Request $request): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // SAMEORIGIN rather than DENY: the dashboard may legitimately want
        // to preview its own public pages in a frame later. Still blocks
        // third-party framing, which is what clickjacking needs.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Full URL to our own origin, origin only cross-site — keeps
        // UTM-tagged landing URLs out of third-party referer logs while
        // preserving attribution on our own domain.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $response->headers->set('Permissions-Policy', implode(', ', [
            'accelerometer=()',
            // autoplay=(self) because the hero background is built to be
            // swapped for a muted looping <video> without markup changes.
            'autoplay=(self)',
            'camera=()',
            'display-capture=()',
            'encrypted-media=()',
            'fullscreen=(self)',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'midi=()',
            'payment=()',
            'usb=()',
        ]));

        // HSTS is only meaningful — and only safe — over HTTPS. Sending it
        // in local http development would pin the browser to https for
        // localhost and break the dev server.
        if ($request->secure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        /*
         * CSP is REPORT-ONLY on purpose.
         *
         * Alpine evaluates its x-* expressions with new Function(), which
         * needs 'unsafe-eval', and the marquee/hero pass their tuned
         * durations through inline style attributes. Report-only surfaces
         * violations in the console without breaking the page.
         *
         * TIGHTEN LATER: the real fix is Alpine's CSP build plus a nonce,
         * at which point both unsafe-* can go and this becomes enforcing.
         */
        $response->headers->set('Content-Security-Policy-Report-Only', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net data:",
            "img-src 'self' data: https:",
            "media-src 'self'",
            "connect-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]));

        // Leaks the PHP version to anyone reading response headers.
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
