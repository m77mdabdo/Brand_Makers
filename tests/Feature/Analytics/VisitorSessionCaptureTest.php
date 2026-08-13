<?php

namespace Tests\Feature\Analytics;

use App\Models\Setting;
use App\Models\User;
use App\Models\VisitorSession;
use App\Services\Analytics\VisitorSessionService;
use App\Services\Analytics\VisitorTrackingGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What actually gets recorded, and — mostly — what does not.
 */
class VisitorSessionCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * A consenting, non-bot visitor. Every assertion about capture starts
     * from here, so the ONE thing being varied in each test is visible.
     */
    private function consenting(): static
    {
        return $this
            // withUnencryptedCookie, not withCookie: the consent cookie is
            // written by JavaScript, so it is excepted from Laravel's
            // cookie encryption and arrives as the literal word. Sending
            // an encrypted one here would test a cookie no browser sends.
            ->withUnencryptedCookie(VisitorTrackingGate::CONSENT_COOKIE, VisitorTrackingGate::CONSENT_GRANTED)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36');
    }

    // -----------------------------------------------------------------
    // Capture
    // -----------------------------------------------------------------

    public function test_a_consenting_visitor_starts_a_session(): void
    {
        $this->consenting()->get('/')->assertOk();

        $session = VisitorSession::sole();

        $this->assertSame('/', $session->entry_url);
        $this->assertSame(1, $session->page_count);
        $this->assertCount(1, $session->events);
        $this->assertSame(VisitorSession::TYPE_PAGEVIEW, $session->events[0]['type']);
    }

    public function test_the_session_cookie_is_issued_httponly_and_lax(): void
    {
        $response = $this->consenting()->get('/');

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === VisitorSessionService::COOKIE);

        $this->assertNotNull($cookie, 'The middleware must issue a session cookie.');
        // httpOnly: nothing on the client needs to read this, and it is
        // the key to a journey row.
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_a_second_request_appends_to_the_same_journey(): void
    {
        $this->consenting()->get('/');

        $uid = VisitorSession::sole()->session_uid;

        // withCookie, ENCRYPTED: unlike the consent cookie, this one is
        // never read by JavaScript, so it is not excepted from Laravel's
        // cookie encryption. Sending it in the clear is how a forged
        // cookie arrives, and the middleware would correctly discard it.
        $this->consenting()->withCookie(VisitorSessionService::COOKIE, $uid)->get('/services');

        $session = VisitorSession::sole();

        $this->assertSame(2, $session->page_count);
        $this->assertSame(['/', '/services'], array_column($session->events, 'url'));
    }

    public function test_the_device_browser_and_platform_are_parsed(): void
    {
        $this->withUnencryptedCookie(VisitorTrackingGate::CONSENT_COOKIE, VisitorTrackingGate::CONSENT_GRANTED)
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1')
            ->get('/');

        $session = VisitorSession::sole();

        $this->assertSame('mobile', $session->device_type);
        $this->assertSame('Safari', $session->browser);
        $this->assertSame('iOS', $session->platform);
    }

    public function test_the_attribution_cookies_are_snapshotted_onto_the_session(): void
    {
        $this->consenting()
            ->withUnencryptedCookie('cm_first_touch', json_encode(['utm_source' => 'google']))
            ->withUnencryptedCookie('cm_latest_touch', json_encode(['utm_source' => 'instagram']))
            ->get('/');

        $session = VisitorSession::sole();

        $this->assertSame('google', $session->attribution['first_touch']['utm_source']);
        // sourcePlatform() reads the latest touch first: that is what
        // immediately preceded the visit.
        $this->assertSame('instagram', $session->sourcePlatform());
    }

    // -----------------------------------------------------------------
    // IP anonymisation
    // -----------------------------------------------------------------

    public function test_the_stored_ip_is_a_truncated_prefix_not_an_address(): void
    {
        $this->consenting()->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])->get('/');

        $session = VisitorSession::sole();

        $this->assertSame('203.0.113.0', $session->ip_prefix);
        $this->assertStringNotContainsString('.77', (string) $session->ip_prefix);
    }

    public function test_an_ipv6_visitor_keeps_only_the_first_48_bits(): void
    {
        $this->consenting()->withServerVariables(['REMOTE_ADDR' => '2001:db8:1234:5678:9abc:def0:1234:5678'])->get('/');

        $this->assertSame('2001:db8:1234::', VisitorSession::sole()->ip_prefix);
    }

    public function test_there_is_no_column_that_could_hold_a_full_address(): void
    {
        $this->consenting()->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])->get('/');

        // Every stored value, not just the one column we expect: this is
        // the assertion that catches a full address arriving somewhere
        // nobody thought to look.
        $this->assertStringNotContainsString(
            '203.0.113.77',
            json_encode(VisitorSession::sole()->getAttributes(), JSON_UNESCAPED_SLASHES)
        );
    }

    // -----------------------------------------------------------------
    // Refusals
    // -----------------------------------------------------------------

    public function test_nothing_is_recorded_without_consent(): void
    {
        $this->get('/')->assertOk();

        // Opt-in: an absent decision is refusal, so an ordinary first-time
        // visitor produces no row at all.
        $this->assertSame(0, VisitorSession::count());
    }

    public function test_a_declined_banner_records_nothing(): void
    {
        $this->withUnencryptedCookie(VisitorTrackingGate::CONSENT_COOKIE, VisitorTrackingGate::CONSENT_DENIED)->get('/');

        $this->assertSame(0, VisitorSession::count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function privacySignals(): array
    {
        return [
            'Do Not Track' => ['DNT'],
            'Global Privacy Control' => ['Sec-GPC'],
        ];
    }

    #[DataProvider('privacySignals')]
    public function test_a_browser_privacy_signal_overrides_a_granted_consent_cookie(string $header): void
    {
        $this->consenting()->withHeader($header, '1')->get('/')->assertOk();

        // The signal is checked BEFORE consent and cannot be overridden by
        // it — a visitor who said yes to the banner and then turned DNT on
        // is not recorded.
        $this->assertSame(0, VisitorSession::count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function botAgents(): array
    {
        return [
            'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            'a headless browser' => ['Mozilla/5.0 HeadlessChrome/120.0.0.0'],
            'curl' => ['curl/8.4.0'],
            'a link preview fetcher' => ['facebookexternalhit/1.1'],
            'an empty user agent' => [''],
        ];
    }

    #[DataProvider('botAgents')]
    public function test_automated_traffic_is_not_recorded(string $userAgent): void
    {
        $this->withUnencryptedCookie(VisitorTrackingGate::CONSENT_COOKIE, VisitorTrackingGate::CONSENT_GRANTED)
            ->withHeader('User-Agent', $userAgent)
            ->get('/');

        $this->assertSame(0, VisitorSession::count());
    }

    public function test_the_admin_switch_stops_recording(): void
    {
        Setting::updateOrCreate(['key' => 'visitor_sessions_enabled'], ['value' => false, 'group' => 'visitor_sessions']);

        $this->consenting()->get('/');

        $this->assertSame(0, VisitorSession::count());
    }

    public function test_the_dashboard_is_never_tracked(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->consenting()->get(route('dashboard'))->assertOk();

        // An admin browsing their own site is not a visitor. The
        // middleware is on the public groups only.
        $this->assertSame(0, VisitorSession::count());
    }

    public function test_a_404_is_not_counted_as_a_page_view(): void
    {
        $this->consenting()->get('/a-page-that-does-not-exist')->assertNotFound();

        $this->assertSame(0, VisitorSession::count());
    }

    public function test_a_non_html_response_is_not_counted(): void
    {
        $this->consenting()->get('/sitemap.xml')->assertOk();

        $this->assertSame(0, VisitorSession::count());
    }

    // -----------------------------------------------------------------
    // What is deliberately not stored
    // -----------------------------------------------------------------

    public function test_the_query_string_is_stripped_from_the_recorded_url(): void
    {
        $this->consenting()->get('/services?utm_source=google&q=something-private');

        $session = VisitorSession::sole();

        $this->assertSame('/services', $session->entry_url);
        $this->assertStringNotContainsString('utm_source', json_encode($session->events));
        $this->assertStringNotContainsString('something-private', json_encode($session->events));
    }

    public function test_an_internal_referrer_is_not_stored(): void
    {
        $this->consenting()->withHeader('referer', url('/services'))->get('/');

        // An internal referrer is just the previous entry in the journey
        // already being recorded.
        $this->assertNull(VisitorSession::sole()->referrer);
    }

    public function test_an_external_referrer_is_stored(): void
    {
        $this->consenting()->withHeader('referer', 'https://www.google.com/search')->get('/');

        $this->assertSame('https://www.google.com/search', VisitorSession::sole()->referrer);
    }

    // -----------------------------------------------------------------
    // The event cap
    // -----------------------------------------------------------------

    public function test_the_journey_stops_growing_at_the_cap_but_the_page_count_does_not(): void
    {
        $session = VisitorSession::factory()->create([
            'events' => array_fill(0, VisitorSession::MAX_EVENTS, [
                'type' => VisitorSession::TYPE_PAGEVIEW, 'url' => '/', 'label' => null,
                'ts' => now()->toIso8601String(), 'duration_ms' => null,
            ]),
            'page_count' => VisitorSession::MAX_EVENTS,
        ]);

        $this->consenting()->withCookie(VisitorSessionService::COOKIE, $session->session_uid)->get('/services');

        $session->refresh();

        $this->assertCount(VisitorSession::MAX_EVENTS, $session->events);
        // A truncated journey still reads as a long one rather than
        // silently looking like a 100-page visit.
        $this->assertSame(VisitorSession::MAX_EVENTS + 1, $session->page_count);
        $this->assertTrue($session->isTruncated());
    }

    // -----------------------------------------------------------------
    // The uid
    // -----------------------------------------------------------------

    public function test_a_hand_crafted_session_cookie_is_replaced_rather_than_trusted(): void
    {
        $this->consenting()->withUnencryptedCookie(VisitorSessionService::COOKIE, 'not-a-real-uid')->get('/');

        $session = VisitorSession::sole();

        $this->assertNotSame('not-a-real-uid', $session->session_uid);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $session->session_uid);
    }
}
