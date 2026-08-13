<?php

namespace App\Http\Middleware;

use App\Services\Analytics\VisitorSessionService;
use App\Services\Analytics\VisitorTrackingGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Appends a pageview to the visitor's journey.
 *
 * THE PERFORMANCE SHAPE, which is the entire reason this is split in two:
 *
 *   handle()    — decides whether to track and issues the cookie. No
 *                 database access at all beyond the gate's settings
 *                 lookup, which SettingsRepository already loads once per
 *                 request and shares with everything else on the page.
 *   terminate() — does the SELECT and the INSERT/UPDATE. Laravel calls
 *                 this after $response->send(), so on FastCGI the visitor
 *                 has the whole page before either query runs. A slow
 *                 write shows up in the worker's occupancy, never in
 *                 time-to-first-byte.
 *
 * Applied to the PUBLIC route groups only. Nothing under /dashboard is
 * tracked: an admin browsing their own site is not a visitor, and
 * recording them would put staff behaviour in a table built for marketing
 * analysis.
 */
class TrackVisitorSession
{
    /**
     * Where handle() leaves the uid for terminate() to pick up.
     *
     * On the REQUEST, not on `$this`. Laravel's kernel resolves terminable
     * middleware out of the container again when it calls terminate(), so
     * unless the class is registered as a singleton that is a different
     * instance and any property set in handle() is gone. The request
     * object is the same one either way, so this needs no container
     * binding to be correct — one less thing that can be silently undone
     * later.
     */
    private const REQUEST_ATTRIBUTE = 'cm.session_uid';

    public function __construct(
        private readonly VisitorTrackingGate $gate,
        private readonly VisitorSessionService $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldTrack($request, $response)) {
            return $response;
        }

        $uid = $this->resolveUid($request->cookie(VisitorSessionService::COOKIE));

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $uid);

        // Re-issued on every tracked response so the 30-minute window
        // slides with activity rather than expiring mid-visit.
        $response->headers->setCookie($this->cookie($uid));

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $uid = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if (! is_string($uid)) {
            return;
        }

        $this->sessions->recordPageview($request, $uid);
    }

    /**
     * A cookie that is not 32 hex characters did not come from here.
     * Regenerating rather than trusting it keeps a hand-crafted value from
     * becoming a row key.
     */
    private function resolveUid(mixed $existing): string
    {
        return $this->sessions->isValidUid($existing)
            ? $existing
            : bin2hex(random_bytes(16));
    }

    private function cookie(string $uid): Cookie
    {
        return new Cookie(
            name: VisitorSessionService::COOKIE,
            value: $uid,
            expire: now()->addMinutes(VisitorSessionService::COOKIE_MINUTES)->getTimestamp(),
            path: '/',
            secure: (bool) config('session.secure', false),
            httpOnly: true,
            // Lax, not Strict: a visitor arriving from an Instagram ad or
            // a WhatsApp link is making a cross-site navigation, and
            // Strict would withhold the cookie on exactly the first
            // pageview this feature most wants to record.
            sameSite: Cookie::SAMESITE_LAX,
        );
    }

    /**
     * Only ordinary, successful, HTML page loads.
     *
     * Redirects, 404s, sitemap.xml, robots.txt and the beacon endpoint are
     * all responses that are not a visitor reading a page, and counting
     * them would inflate page_count with things nobody looked at.
     */
    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        if (! str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')) {
            return false;
        }

        return $this->gate->allows($request);
    }
}
