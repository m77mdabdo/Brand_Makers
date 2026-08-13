<?php

namespace Tests\Feature\Analytics;

use App\Models\Setting;
use App\Models\TrackingSetting;
use App\Services\Analytics\VisitorTrackingGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * No pixel fires before consent.
 *
 * The assertions here are about what is IN the page, because that is the
 * only thing a server-side test can see — but the distinction being
 * tested is real: for Meta and TikTok the vendor script is never fetched,
 * and for Google the tag loads in a state where it stores nothing.
 */
class MarketingConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        // Every platform configured and switched on, so each test is
        // varying consent alone.
        foreach ([
            'meta_pixel_id' => '123456789012345',
            'tiktok_pixel_id' => 'CABCDEFGHIJKLMNOPQRS',
            'ga4_measurement_id' => 'G-ABCDEF1234',
            'gtm_container_id' => 'GTM-ABCD123',
        ] as $key => $value) {
            TrackingSetting::updateOrCreate(['key' => $key], ['value' => $value, 'is_active' => true]);
        }
    }

    private function homepage(bool $consented = false, array $headers = []): TestResponse
    {
        $test = $this->withHeaders($headers);

        if ($consented) {
            $test = $test->withUnencryptedCookie(
                VisitorTrackingGate::CONSENT_COOKIE,
                VisitorTrackingGate::CONSENT_GRANTED
            );
        }

        return $test->get('/');
    }

    // -----------------------------------------------------------------
    // Before consent
    // -----------------------------------------------------------------

    public function test_google_loads_with_every_consent_signal_denied(): void
    {
        $content = $this->homepage()->getContent();

        // Consent Mode v2: the tag is present but restricted, which is
        // what keeps the modelled conversion data while storing nothing.
        $this->assertStringContainsString("gtag('consent', 'default'", $content);
        $this->assertStringContainsString("'ad_storage': 'denied'", $content);
        $this->assertStringContainsString("'analytics_storage': 'denied'", $content);

        // …and it is NOT granted.
        $this->assertStringNotContainsString('window.cmConsent.grantGoogle();', $content);
    }

    public function test_the_consent_defaults_come_before_the_google_tag(): void
    {
        $content = $this->homepage()->getContent();

        // The ordering IS the mechanism: a tag that runs before the
        // defaults has already run once unrestricted.
        $this->assertLessThan(
            strpos($content, 'googletagmanager.com'),
            strpos($content, "gtag('consent', 'default'"),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deferredVendors(): array
    {
        return [
            'Meta' => ['connect.facebook.net'],
            'TikTok' => ['analytics.tiktok.com'],
        ];
    }

    #[DataProvider('deferredVendors')]
    public function test_the_deferred_pixel_is_defined_but_never_invoked_before_consent(string $vendorHost): void
    {
        $content = $this->homepage()->getContent();

        // The snippet is on the page, inside a function nothing calls —
        // so no request reaches the vendor.
        $this->assertStringContainsString($vendorHost, $content);
        $this->assertStringNotContainsString('window.cmPixels.load();', $content);
    }

    public function test_the_meta_noscript_pixel_is_withheld_before_consent(): void
    {
        // An <img> fires on parse with no JavaScript involved, so the
        // client-side loader cannot hold it back — it has to be gated
        // server-side.
        $this->homepage()->assertDontSee('facebook.com/tr?id=', false);
    }

    // -----------------------------------------------------------------
    // After consent
    // -----------------------------------------------------------------

    public function test_a_returning_consenting_visitor_gets_everything_from_the_first_paint(): void
    {
        $content = $this->homepage(consented: true)->getContent();

        $this->assertStringContainsString('window.cmConsent.grantGoogle();', $content);
        $this->assertStringContainsString('window.cmPixels.load();', $content);
        $this->assertStringContainsString('facebook.com/tr?id=123456789012345', $content);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function privacySignalHeaders(): array
    {
        return [
            'Do Not Track' => ['DNT'],
            'Global Privacy Control' => ['Sec-GPC'],
        ];
    }

    #[DataProvider('privacySignalHeaders')]
    public function test_a_privacy_signal_withholds_the_pixels_even_from_a_consenting_visitor(string $header): void
    {
        $content = $this->homepage(consented: true, headers: [$header => '1'])->getContent();

        $this->assertStringNotContainsString('window.cmConsent.grantGoogle();', $content);
        $this->assertStringNotContainsString('window.cmPixels.load();', $content);
        $this->assertStringNotContainsString('facebook.com/tr?id=', $content);
    }

    public function test_turning_off_journey_recording_does_not_disable_the_pixels(): void
    {
        Setting::updateOrCreate(['key' => 'visitor_sessions_enabled'], ['value' => false, 'group' => 'visitor_sessions']);

        // That switch says "record visitor journeys". It must not quietly
        // also mean "stop advertising".
        $this->assertStringContainsString(
            'window.cmPixels.load();',
            $this->homepage(consented: true)->getContent(),
        );
    }

    // -----------------------------------------------------------------
    // Nothing configured
    // -----------------------------------------------------------------

    public function test_an_inactive_platform_renders_nothing_at_all(): void
    {
        TrackingSetting::query()->update(['is_active' => false]);

        $content = $this->homepage(consented: true)->getContent();

        $this->assertStringNotContainsString('connect.facebook.net', $content);
        $this->assertStringNotContainsString('googletagmanager.com', $content);
        $this->assertStringNotContainsString('analytics.tiktok.com', $content);
    }

    public function test_a_platform_with_an_empty_value_renders_nothing(): void
    {
        TrackingSetting::query()->update(['value' => null]);

        $this->assertStringNotContainsString('connect.facebook.net', $this->homepage(consented: true)->getContent());
    }
}
