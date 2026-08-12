<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The baseline hardening that has to hold on every response, including
 * the ones the framework renders itself.
 */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Public pages, an auth screen, and BOTH error paths.
     *
     * The error entries are the ones that matter: exception responses are
     * produced by the handler and never travel back out through the
     * middleware stack, so without the handler re-applying the headers
     * they would ship with none at all.
     *
     * @return array<string, array{string, int}>
     */
    public static function responses(): array
    {
        return [
            'homepage' => ['/', 200],
            'english homepage' => ['/en', 200],
            'login' => ['/login', 200],
            'sitemap' => ['/sitemap.xml', 200],
            '404 (rendered by the exception handler)' => ['/no-such-page', 404],
            '404 under the en prefix' => ['/en/no-such-page', 404],
        ];
    }

    #[DataProvider('responses')]
    public function test_security_headers_are_present(string $url, int $status): void
    {
        $this->seed();

        $response = $this->get($url);

        $response->assertStatus($status);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->assertNotNull($response->headers->get('Permissions-Policy'));
        $this->assertNotNull(
            $response->headers->get('Content-Security-Policy-Report-Only'),
            "No CSP on [{$url}]."
        );
    }

    #[DataProvider('responses')]
    public function test_the_php_version_is_not_advertised(string $url): void
    {
        $this->seed();

        $this->assertNull($this->get($url)->headers->get('X-Powered-By'));
    }

    public function test_dashboard_responses_are_hardened_too(): void
    {
        $this->seed();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_a_403_still_carries_the_headers(): void
    {
        $this->seed();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    // -----------------------------------------------------------------
    // Trusted proxies / HTTPS
    // -----------------------------------------------------------------

    public function test_trusted_proxies_are_configured(): void
    {
        // Configured in AppServiceProvider::boot(), not in
        // bootstrap/app.php — config() is not bound while the middleware
        // stack is being built, and env() there would silently return null
        // under a cached config.
        $reflection = new \ReflectionClass(TrustProxies::class);
        $property = $reflection->getProperty('alwaysTrustProxies');
        $property->setAccessible(true);

        $this->assertNotNull($property->getValue(), 'Trusted proxies were never configured.');
        $this->assertSame(config('trustedproxy.proxies'), $property->getValue());
    }

    public function test_the_proxy_list_can_be_narrowed_from_the_environment(): void
    {
        // '*' is only correct behind a balancer that strips inbound
        // X-Forwarded-* headers; anything else must be able to pin it.
        $config = require config_path('trustedproxy.php');

        $this->assertArrayHasKey('proxies', $config);
    }

    public function test_the_session_cookie_is_secure_only_by_configuration(): void
    {
        // Driven by SESSION_SECURE_COOKIE so it can be true in every
        // HTTPS environment without breaking local http development,
        // where a secure cookie is never sent at all.
        $this->assertSame(env('SESSION_SECURE_COOKIE'), config('session.secure'));
    }

    // -----------------------------------------------------------------
    // Error pages
    // -----------------------------------------------------------------

    public function test_the_404_page_is_branded_and_localised(): void
    {
        $this->seed();

        $arabic = $this->get('/no-such-page');
        $arabic->assertNotFound();
        $arabic->assertSee('<html lang="ar" dir="rtl">', false);
        $arabic->assertSee(__('site.errors.not_found.title', [], 'ar'), false);
        $arabic->assertSee('logo-light.png', false);

        // Under /en the router matched nothing, so the app locale is the
        // default — the page still has to offer a way across.
        $english = $this->get('/en/no-such-page');
        $english->assertNotFound();
        $english->assertSee(__('site.errors.back_home'), false);
    }

    public function test_the_403_page_is_branded(): void
    {
        $this->seed();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertSee(__('site.errors.forbidden.title'), false);
    }

    // -----------------------------------------------------------------
    // Sitemap and robots
    // -----------------------------------------------------------------

    public function test_the_sitemap_lists_both_locales_of_each_page_once(): void
    {
        $this->seed();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = $response->getContent();

        $this->assertStringContainsString('<loc>'.route('home').'</loc>', $xml);
        $this->assertStringContainsString('hreflang="en" href="'.route('home.en').'"', $xml);
        $this->assertStringContainsString('hreflang="x-default" href="'.route('home').'"', $xml);

        // One <url> per page, with the other locale as an alternate —
        // listing / and /en separately would tell a crawler they are
        // competing documents rather than translations.
        $this->assertSame(
            substr_count($xml, '<loc>'),
            substr_count($xml, 'hreflang="x-default"')
        );
    }

    public function test_the_sitemap_omits_routes_that_do_not_exist_yet(): void
    {
        $this->seed();

        $xml = $this->get('/sitemap.xml')->getContent();

        // The static list names the eventual full sitemap; an entry for a
        // page that has not been built would be a soft 404 in a crawler's
        // queue. /estimator and /booking are still unbuilt.
        $this->assertStringNotContainsString('/estimator', $xml);
        $this->assertStringNotContainsString('/booking', $xml);

        // …and a section that HAS been built appears without any change
        // to the sitemap itself.
        $this->assertStringContainsString('/about', $xml);
        $this->assertStringContainsString('/packages', $xml);
        $this->assertStringContainsString('/services', $xml);
    }

    public function test_the_sitemap_is_valid_xml(): void
    {
        $this->seed();

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($this->get('/sitemap.xml')->getContent());
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($document);
    }

    public function test_robots_disallows_the_staff_areas_and_points_at_the_sitemap(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /dashboard', $robots);
        $this->assertStringContainsString('Disallow: /login', $robots);
        $this->assertStringContainsString('Sitemap:', $robots);
    }
}
