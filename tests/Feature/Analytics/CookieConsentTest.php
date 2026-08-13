<?php

namespace Tests\Feature\Analytics;

use App\Models\Setting;
use App\Services\Analytics\VisitorTrackingGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The banner, and the gate it feeds.
 */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    // -----------------------------------------------------------------
    // The gate's decision table
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{array<string, string>, array<string, string>, ?string}>
     */
    public static function decisions(): array
    {
        $granted = [VisitorTrackingGate::CONSENT_COOKIE => VisitorTrackingGate::CONSENT_GRANTED];

        return [
            'granted' => [$granted, [], null],
            'no decision at all' => [[], [], 'no_consent'],
            'explicitly declined' => [[VisitorTrackingGate::CONSENT_COOKIE => VisitorTrackingGate::CONSENT_DENIED], [], 'no_consent'],
            'a nonsense cookie value' => [[VisitorTrackingGate::CONSENT_COOKIE => 'yes-please'], [], 'no_consent'],
            'DNT beats consent' => [$granted, ['DNT' => '1'], 'dnt'],
            'GPC beats consent' => [$granted, ['Sec-GPC' => '1'], 'dnt'],
            'a bot that somehow consented' => [$granted, ['User-Agent' => 'Googlebot/2.1'], 'bot'],
        ];
    }

    /**
     * @param  array<string, string>  $cookies
     * @param  array<string, string>  $headers
     */
    #[DataProvider('decisions')]
    public function test_the_gate_gives_the_reason_it_refuses(array $cookies, array $headers, ?string $expected): void
    {
        $headers += ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0 Safari/537.36'];

        $request = Request::create('/', 'GET', [], $cookies, [], array_combine(
            array_map(fn ($key) => 'HTTP_'.strtoupper(str_replace('-', '_', $key)), array_keys($headers)),
            array_values($headers),
        ));

        $this->assertSame($expected, app(VisitorTrackingGate::class)->reasonToRefuse($request));
    }

    /**
     * An absent decision and an explicit "no" MUST be the same answer.
     * That is what makes this opt-in rather than opt-out, and it is the
     * one property of the gate that a well-meaning refactor is most likely
     * to break.
     */
    public function test_ignoring_the_banner_is_treated_exactly_like_declining(): void
    {
        $absent = Request::create('/');
        $declined = Request::create('/', 'GET', [], [VisitorTrackingGate::CONSENT_COOKIE => VisitorTrackingGate::CONSENT_DENIED]);

        $gate = app(VisitorTrackingGate::class);

        $this->assertSame($gate->reasonToRefuse($absent), $gate->reasonToRefuse($declined));
        $this->assertFalse($gate->allows($absent));
        $this->assertFalse($gate->allows($declined));
        $this->assertFalse($gate->marketingAllowed($absent));
        $this->assertFalse($gate->marketingAllowed($declined));
    }

    /**
     * The two gates share a consent cookie and a DNT override but NOT the
     * admin switch: turning journey recording off must not silently
     * disable the advertising tags, which nothing in that switch's label
     * says it does.
     */
    public function test_the_admin_switch_governs_recording_but_not_the_marketing_tags(): void
    {
        Setting::updateOrCreate(['key' => 'visitor_sessions_enabled'], ['value' => false, 'group' => 'visitor_sessions']);

        $request = Request::create('/', 'GET', [], [
            VisitorTrackingGate::CONSENT_COOKIE => VisitorTrackingGate::CONSENT_GRANTED,
        ]);

        $gate = app(VisitorTrackingGate::class);

        $this->assertSame('disabled', $gate->reasonToRefuse($request));
        $this->assertTrue($gate->marketingAllowed($request));
    }

    public function test_a_privacy_signal_refuses_the_marketing_tags_too(): void
    {
        foreach (['HTTP_DNT' => '1', 'HTTP_SEC_GPC' => '1'] as $header => $value) {
            $request = Request::create('/', 'GET', [], [
                VisitorTrackingGate::CONSENT_COOKIE => VisitorTrackingGate::CONSENT_GRANTED,
            ], [], [$header => $value]);

            // A visitor cannot be refused by one subsystem and tracked by
            // the other.
            $this->assertFalse(app(VisitorTrackingGate::class)->marketingAllowed($request), $header);
        }
    }

    // -----------------------------------------------------------------
    // Retention window
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{mixed, int}>
     */
    public static function retentionValues(): array
    {
        return [
            'the seeded default' => [90, 90],
            'a custom window' => [30, 30],
            'zero is clamped up' => [0, 1],
            'a negative is clamped up' => [-5, 1],
            'an absurd window is clamped down' => [99999, 3650],
            'a non-integer falls back' => ['forever', 90],
        ];
    }

    #[DataProvider('retentionValues')]
    public function test_the_retention_window_is_clamped_rather_than_trusted(mixed $stored, int $expected): void
    {
        // This number reaches a DELETE, so a 0 would mean "delete
        // everything, including the session open right now".
        Setting::updateOrCreate(['key' => 'visitor_sessions_retention_days'], ['value' => $stored, 'group' => 'visitor_sessions']);

        $this->assertSame($expected, app(VisitorTrackingGate::class)->retentionDays());
    }

    // -----------------------------------------------------------------
    // The banner itself
    // -----------------------------------------------------------------

    public function test_the_banner_renders_on_the_public_site_in_both_locales(): void
    {
        $this->get('/')->assertSee(__('site.consent.accept', [], 'ar'), false);
        $this->get('/en')->assertSee(__('site.consent.accept', [], 'en'), false);
    }

    public function test_declining_is_offered_first_and_as_a_real_button(): void
    {
        $content = $this->get('/')->getContent();

        // Decline before accept in the DOM, and both are buttons. A banner
        // where refusing is harder than accepting is not consent.
        $declinePosition = strpos($content, 'decline()');
        $acceptPosition = strpos($content, 'accept()');

        $this->assertNotFalse($declinePosition);
        $this->assertNotFalse($acceptPosition);
        $this->assertLessThan($acceptPosition, $declinePosition);
    }

    public function test_the_banner_links_to_the_privacy_policy_in_the_current_locale(): void
    {
        $this->get('/')->assertSee(route('pages.privacy-policy'), false);
        $this->get('/en')->assertSee(route('pages.privacy-policy.en'), false);
    }

    public function test_the_banner_disappears_when_recording_is_switched_off(): void
    {
        Setting::updateOrCreate(['key' => 'visitor_sessions_enabled'], ['value' => false, 'group' => 'visitor_sessions']);

        // Asking permission for something that is not happening is noise.
        $this->get('/')->assertDontSee(__('site.consent.accept', [], 'ar'), false);
    }

    // -----------------------------------------------------------------
    // The beacon endpoint is advertised
    // -----------------------------------------------------------------

    public function test_the_layout_carries_the_beacon_endpoint_and_a_csrf_token(): void
    {
        // sendBeacon cannot set headers, so the token has to be readable
        // from the page and travel as a form field.
        $this->get('/')
            ->assertSee('name="visitor-beacon-url"', false)
            ->assertSee('name="csrf-token"', false);
    }
}
