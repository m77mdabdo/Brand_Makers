<?php

namespace App\Services\Analytics;

use App\Models\Lead;
use App\Models\VisitorSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Owns every write to visitor_sessions.
 *
 * COST. One indexed SELECT on the unique session_uid, then one INSERT or
 * one UPDATE. Nothing here fans out, and the middleware calls it from
 * terminate() — after the response has already gone to the browser — so
 * those two queries are off the visitor's critical path entirely.
 *
 * WHAT IS DELIBERATELY NOT STORED. Query strings are stripped from every
 * URL before it is written. The query is the one part of a request that
 * routinely carries arbitrary input (a search term, an email in a shared
 * link, a one-time token), and the only query parameters this feature
 * actually needs — the UTM and click-id set — are already captured
 * structurally in `attribution`. Keeping the raw query would be collecting
 * more than the feature uses, which is what a retention policy exists to
 * prevent.
 *
 * HOW THIS COMPLEMENTS ATTRIBUTION RATHER THAN DUPLICATING IT. The
 * `attribution` column here is a COPY of the same first/latest-touch
 * cookies the public forms submit as hidden fields, kept so a journey
 * reads on its own in the dashboard. AttributionService remains the single
 * source of truth for what lands on a Lead; nothing in this class writes
 * to `leads`.
 */
class VisitorSessionService
{
    /**
     * A visit resumes an existing journey while this cookie lives, and
     * starts a new one once it lapses. 30 minutes is the analytics
     * convention, and it is what makes "session" mean a sitting rather
     * than a lifetime.
     */
    public const COOKIE = 'cm_session';

    public const COOKIE_MINUTES = 30;

    /**
     * The column is varchar(2048); this leaves room and keeps a
     * pathological URL from being the reason a write fails.
     */
    private const MAX_URL_LENGTH = 1000;

    private const MAX_LABEL_LENGTH = 120;

    public function __construct(
        private readonly IpAnonymizer $ipAnonymizer,
        private readonly UserAgentParser $userAgents,
        private readonly VisitorTrackingGate $gate,
    ) {}

    /**
     * The one call the form controllers make: log what the visitor did
     * and, when it produced a lead, link the journey to it.
     *
     * The gate, cookie and uid handling all live here rather than in three
     * controllers, so a form added later cannot record a conversion
     * without the same consent check the middleware applies. A refusal is
     * silent — the form still works, there is simply no journey to write
     * to.
     */
    public function recordConversion(Request $request, string $label, ?Lead $lead = null): void
    {
        if (! $this->gate->allows($request)) {
            return;
        }

        $uid = $request->cookie(self::COOKIE);

        // No cookie means no journey: a visitor whose first ever request
        // is the form POST itself, which the middleware never saw because
        // it only tracks GETs.
        if (! $this->isValidUid($uid)) {
            return;
        }

        $this->recordAction($request, $uid, $label);

        if ($lead !== null) {
            $this->linkLead($uid, $lead);
        }
    }

    /**
     * Records a page view, starting the session if this is the first one.
     */
    public function recordPageview(Request $request, string $sessionUid): ?VisitorSession
    {
        $session = VisitorSession::where('session_uid', $sessionUid)->first();

        if (! $session) {
            return $this->start($request, $sessionUid);
        }

        return $this->append($session, VisitorSession::TYPE_PAGEVIEW, $this->path($request), null, $request);
    }

    /**
     * A non-navigation event: a form submitted, a CTA used.
     *
     * Distinct from a pageview so the timeline can show what the visitor
     * DID, and so page_count stays a count of pages.
     */
    public function recordAction(Request $request, string $sessionUid, string $label): ?VisitorSession
    {
        $session = VisitorSession::where('session_uid', $sessionUid)->first();

        if (! $session) {
            return null;
        }

        return $this->append(
            $session,
            VisitorSession::TYPE_ACTION,
            $this->path($request),
            Str::limit($label, self::MAX_LABEL_LENGTH, ''),
            $request,
            countsAsPage: false,
        );
    }

    /**
     * Links the session to the lead it produced.
     *
     * Idempotent, and it never overwrites an existing link: if a visitor
     * submits twice in one sitting the journey belongs to the conversion
     * that ended it, and silently repointing it would lose that.
     */
    public function linkLead(string $sessionUid, Lead $lead): void
    {
        VisitorSession::where('session_uid', $sessionUid)
            ->whereNull('lead_id')
            ->update(['lead_id' => $lead->getKey()]);
    }

    /**
     * The client beacon's report: how long the visitor actually spent on
     * one page.
     *
     * Applied to the most recent event for THAT path rather than to the
     * last event outright — a beacon can arrive after the visitor has
     * already navigated on, and attaching 40 seconds of reading to the
     * wrong page is worse than dropping it.
     *
     * total_duration_seconds is derived from started_at → last_activity_at
     * rather than summed from the per-page numbers: the sum undercounts
     * every gap the beacon missed, and a monotonic span cannot go
     * backwards when two beacons race.
     */
    public function recordDuration(string $sessionUid, string $url, int $durationMs): ?VisitorSession
    {
        $session = VisitorSession::where('session_uid', $sessionUid)->first();

        if (! $session) {
            return null;
        }

        $path = $this->normaliseUrl($url);
        $events = $session->events ?? [];

        for ($i = count($events) - 1; $i >= 0; $i--) {
            if (($events[$i]['url'] ?? null) === $path) {
                // Accumulate rather than replace: a visitor who tabs away
                // and back sends two beacons for the same page view, and
                // the time they spent is the total, not the last slice.
                $events[$i]['duration_ms'] = (int) ($events[$i]['duration_ms'] ?? 0) + $durationMs;
                break;
            }
        }

        $session->events = $events;
        $session->last_activity_at = now();
        $session->total_duration_seconds = $this->elapsedSeconds($session);
        $session->save();

        return $session;
    }

    /**
     * A session uid this application issued: 32 hex characters and nothing
     * else. Anything hand-crafted is regenerated rather than trusted, so
     * a chosen value can never become a row key.
     */
    public function isValidUid(mixed $uid): bool
    {
        return is_string($uid) && preg_match('/^[a-f0-9]{32}$/', $uid) === 1;
    }

    private function start(Request $request, string $sessionUid): VisitorSession
    {
        $agent = $this->userAgents->parse($request->userAgent());
        $now = now();
        $path = $this->path($request);

        return VisitorSession::create([
            'session_uid' => $sessionUid,
            'started_at' => $now,
            'last_activity_at' => $now,
            'entry_url' => $path,
            'referrer' => $this->externalReferrer($request),
            'attribution' => $this->attributionSnapshot($request),
            'device_type' => $agent['device_type'],
            'browser' => $agent['browser'],
            'platform' => $agent['platform'],
            // Truncated by IpAnonymizer here AND again by the model's
            // AnonymizesIp mutator. Belt and braces on the one column
            // where getting it wrong is a privacy incident rather than a
            // bug.
            'ip_prefix' => $this->ipAnonymizer->anonymize($request->ip()),
            'country' => $this->country($request),
            'events' => [$this->event(VisitorSession::TYPE_PAGEVIEW, $path, null, $now)],
            'page_count' => 1,
            'total_duration_seconds' => 0,
        ]);
    }

    private function append(
        VisitorSession $session,
        string $type,
        string $url,
        ?string $label,
        Request $request,
        bool $countsAsPage = true,
    ): VisitorSession {
        $events = $session->events ?? [];

        // The cap. Past it, appends are dropped but page_count keeps
        // counting, so a truncated journey still reads as a long one —
        // see VisitorSession::MAX_EVENTS for why this exists at all.
        if (count($events) < VisitorSession::MAX_EVENTS) {
            $events[] = $this->event($type, $url, $label, now());
            $session->events = $events;
        }

        if ($countsAsPage) {
            $session->page_count = $session->page_count + 1;
        }

        $session->last_activity_at = now();
        $session->total_duration_seconds = $this->elapsedSeconds($session);

        // Refresh the latest touch if the visitor arrived on a new
        // campaign mid-session — rare, but the alternative is attributing
        // the whole journey to whatever brought them in first, which is
        // the opposite of what "latest touch" means everywhere else in
        // this codebase.
        $attribution = $session->attribution ?? [];
        $latest = $this->decodeTouchCookie($request, 'cm_latest_touch');

        if ($latest !== null && $latest !== ($attribution['latest_touch'] ?? null)) {
            $attribution['latest_touch'] = $latest;
            $session->attribution = $attribution;
        }

        $session->save();

        return $session;
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $type, string $url, ?string $label, \DateTimeInterface $at): array
    {
        return [
            'type' => $type,
            'url' => $url,
            'label' => $label,
            'ts' => $at->format(\DateTimeInterface::ATOM),
            'duration_ms' => null,
        ];
    }

    private function elapsedSeconds(VisitorSession $session): int
    {
        return max(0, (int) $session->started_at->diffInSeconds($session->last_activity_at));
    }

    /**
     * Path only — no scheme, host or query string. See the class docblock
     * for why the query goes.
     */
    private function path(Request $request): string
    {
        return $this->normaliseUrl($request->getPathInfo());
    }

    private function normaliseUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return Str::limit($path, self::MAX_URL_LENGTH, '');
    }

    /**
     * Only where the visitor came from OFF this site. An internal referrer
     * is just the previous entry in the journey already being recorded,
     * and storing it would duplicate the timeline in a column.
     */
    private function externalReferrer(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');

        if (! $referrer) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if ($host === null || $host === $request->getHost()) {
            return null;
        }

        return Str::limit($referrer, self::MAX_URL_LENGTH, '');
    }

    /**
     * A snapshot of the same first/latest-touch cookies the public forms
     * submit as hidden fields — copied, not referenced, so a session reads
     * on its own.
     *
     * @return array<string, mixed>|null
     */
    private function attributionSnapshot(Request $request): ?array
    {
        $snapshot = array_filter([
            'first_touch' => $this->decodeTouchCookie($request, 'cm_first_touch'),
            'latest_touch' => $this->decodeTouchCookie($request, 'cm_latest_touch'),
        ]);

        return $snapshot === [] ? null : $snapshot;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeTouchCookie(Request $request, string $name): ?array
    {
        $raw = $request->cookie($name);

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Country as the edge reports it.
     *
     * There is no GeoIP database in this project and adding one for a
     * dashboard column is not worth the maintenance. Behind Cloudflare or
     * a similar proxy the header is already there; without one this stays
     * null rather than guessing.
     */
    private function country(Request $request): ?string
    {
        $country = $request->headers->get('CF-IPCountry')
            ?? $request->headers->get('X-Country-Code');

        if (! is_string($country) || preg_match('/^[A-Za-z]{2}$/', $country) !== 1) {
            return null;
        }

        return strtoupper($country);
    }
}
