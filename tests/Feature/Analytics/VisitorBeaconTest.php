<?php

namespace Tests\Feature\Analytics;

use App\Models\VisitorSession;
use App\Services\Analytics\VisitorSessionService;
use App\Services\Analytics\VisitorTrackingGate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The time-on-page beacon.
 *
 * Every outcome — success, refusal, garbage — is a 204 with no body. The
 * page that issued the beacon is usually already gone, so there is nobody
 * to tell anything to, and saying whether a session exists would be a
 * disclosure with no reader.
 */
class VisitorBeaconTest extends TestCase
{
    use RefreshDatabase;

    private VisitorSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->session = VisitorSession::factory()->create([
            'events' => [
                ['type' => VisitorSession::TYPE_PAGEVIEW, 'url' => '/services', 'label' => null, 'ts' => now()->toIso8601String(), 'duration_ms' => null],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function beacon(array $payload, bool $consented = true, ?string $uid = null): TestResponse
    {
        $test = $this;

        if ($consented) {
            $test = $test->withUnencryptedCookie(
                VisitorTrackingGate::CONSENT_COOKIE,
                VisitorTrackingGate::CONSENT_GRANTED
            );
        }

        // Encrypted, because cm_session is not excepted from cookie
        // encryption — this is how a real browser sends it back.
        $test = $test->withCookie(VisitorSessionService::COOKIE, $uid ?? $this->session->session_uid);

        return $test
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0 Safari/537.36')
            ->post(route('visitor.beacon'), $payload);
    }

    public function test_a_valid_beacon_records_time_against_the_matching_page(): void
    {
        $this->beacon(['url' => '/services', 'duration_ms' => 4200])->assertNoContent();

        $this->assertSame(4200, $this->session->fresh()->events[0]['duration_ms']);
    }

    public function test_two_beacons_for_the_same_page_accumulate(): void
    {
        $this->beacon(['url' => '/services', 'duration_ms' => 3000]);
        $this->beacon(['url' => '/services', 'duration_ms' => 2000]);

        // A visitor who tabs away and back sends two reports, and the time
        // they spent is the total rather than the last slice.
        $this->assertSame(5000, $this->session->fresh()->events[0]['duration_ms']);
    }

    public function test_a_beacon_for_a_page_not_in_the_journey_changes_no_duration(): void
    {
        $this->beacon(['url' => '/somewhere-else', 'duration_ms' => 9000])->assertNoContent();

        // Attaching time to the wrong page would be worse than dropping
        // it.
        $this->assertNull($this->session->fresh()->events[0]['duration_ms']);
    }

    public function test_the_query_string_is_ignored_when_matching_the_page(): void
    {
        $this->beacon(['url' => '/services?utm_source=google', 'duration_ms' => 1500]);

        $this->assertSame(1500, $this->session->fresh()->events[0]['duration_ms']);
    }

    // -----------------------------------------------------------------
    // Silent refusals
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function rejectedPayloads(): array
    {
        return [
            'no url' => [['duration_ms' => 1000]],
            'no duration' => [['url' => '/services']],
            'a negative duration' => [['url' => '/services', 'duration_ms' => -1]],
            'a duration past the cap' => [['url' => '/services', 'duration_ms' => 31 * 60 * 1000]],
            'a non-numeric duration' => [['url' => '/services', 'duration_ms' => 'ages']],
            'an over-long url' => [['url' => '/'.str_repeat('a', 1200), 'duration_ms' => 1000]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('rejectedPayloads')]
    public function test_a_bad_payload_is_a_silent_204_rather_than_a_redirect(array $payload): void
    {
        // $request->validate() would throw and become a 302 back to the
        // previous page — which sendBeacon would fetch and discard.
        $this->beacon($payload)->assertNoContent();

        $this->assertNull($this->session->fresh()->events[0]['duration_ms']);
    }

    public function test_a_beacon_without_consent_is_a_silent_204(): void
    {
        $this->beacon(['url' => '/services', 'duration_ms' => 4200], consented: false)->assertNoContent();

        $this->assertNull($this->session->fresh()->events[0]['duration_ms']);
    }

    public function test_a_privacy_signal_refuses_the_beacon(): void
    {
        $this->withUnencryptedCookie(VisitorTrackingGate::CONSENT_COOKIE, VisitorTrackingGate::CONSENT_GRANTED)
            ->withCookie(VisitorSessionService::COOKIE, $this->session->session_uid)
            ->withHeader('Sec-GPC', '1')
            ->post(route('visitor.beacon'), ['url' => '/services', 'duration_ms' => 4200])
            ->assertNoContent();

        $this->assertNull($this->session->fresh()->events[0]['duration_ms']);
    }

    public function test_a_forged_session_uid_cannot_report_into_someone_elses_journey(): void
    {
        // The session is identified by the httpOnly cookie and never by
        // anything in the payload — but a caller who knows a uid still
        // cannot use it, because a hand-set cookie is not an encrypted
        // one.
        $this->withUnencryptedCookie(VisitorTrackingGate::CONSENT_COOKIE, VisitorTrackingGate::CONSENT_GRANTED)
            ->withUnencryptedCookie(VisitorSessionService::COOKIE, $this->session->session_uid)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0 Safari/537.36')
            ->post(route('visitor.beacon'), ['url' => '/services', 'duration_ms' => 4200])
            ->assertNoContent();

        $this->assertNull($this->session->fresh()->events[0]['duration_ms']);
    }

    public function test_a_beacon_for_an_unknown_session_is_a_silent_204(): void
    {
        $this->beacon(['url' => '/services', 'duration_ms' => 1000], uid: str_repeat('a', 32))->assertNoContent();
    }

    public function test_a_beacon_with_a_malformed_uid_is_a_silent_204(): void
    {
        $this->beacon(['url' => '/services', 'duration_ms' => 1000], uid: 'not-a-uid')->assertNoContent();
    }

    // -----------------------------------------------------------------
    // CSRF and the middleware boundary
    // -----------------------------------------------------------------

    /**
     * The endpoint is in the `web` group and is NOT excepted from CSRF.
     *
     * It cannot be asserted by posting without a token: Laravel's
     * ValidateCsrfToken short-circuits under `runningUnitTests()`, so
     * every test post would pass whether the protection were there or
     * not. What CAN be asserted is the two things that would have to be
     * undone to remove it — the group membership, and the absence of an
     * exception — plus the client-side half that makes it workable.
     */
    public function test_the_endpoint_keeps_csrf_protection(): void
    {
        $middleware = app('router')->getRoutes()->getByName('visitor.beacon')->gatherMiddleware();

        $this->assertContains('web', $middleware);
        $this->assertContains('throttle:60,1', $middleware);

        $csrf = new ValidateCsrfToken(app(), app('encrypter'));

        $this->assertEmpty(
            (fn () => $this->except)->call($csrf),
            'The beacon must not be added to the CSRF exception list.'
        );

        // The half that makes CSRF workable from sendBeacon, which cannot
        // set headers: the token travels as a form field.
        $this->assertStringContainsString(
            "payload.append('_token', token);",
            file_get_contents(resource_path('js/visitor-session.js')),
        );
    }

    public function test_the_beacon_itself_is_never_recorded_as_a_page_view(): void
    {
        $before = $this->session->fresh()->page_count;

        $this->beacon(['url' => '/services', 'duration_ms' => 1000]);

        // It is a POST, and the tracking middleware is not on this route
        // at all — so it can never inflate page_count.
        $this->assertSame($before, $this->session->fresh()->page_count);
    }
}
