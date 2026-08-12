<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The bilingual routing contract.
 *
 * Arabic is the default and carries no prefix; English lives under /en
 * with a ".en" name suffix. The route NAME is what decides the locale, so
 * the router's decision and the rendered language can never disagree.
 */
class LocaleRoutingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function publicRoutes(): array
    {
        return [
            'home' => ['home'],
            'about' => ['pages.about'],
            'our process' => ['pages.our-process'],
            'privacy policy' => ['pages.privacy-policy'],
            'terms' => ['pages.terms-and-conditions'],
        ];
    }

    #[DataProvider('publicRoutes')]
    public function test_every_public_route_is_registered_in_both_locales(string $name): void
    {
        $this->assertTrue(Route::has($name), "Missing Arabic route [{$name}].");
        $this->assertTrue(Route::has("{$name}.en"), "Missing English route [{$name}.en].");
    }

    #[DataProvider('publicRoutes')]
    public function test_the_english_variant_lives_under_the_en_prefix(string $name): void
    {
        $arabic = parse_url(route($name), PHP_URL_PATH) ?? '/';
        $english = parse_url(route("{$name}.en"), PHP_URL_PATH) ?? '/';

        $this->assertStringStartsNotWith('/en', $arabic, 'Arabic is the default and takes no prefix.');
        $this->assertTrue(
            $english === '/en' || str_starts_with($english, '/en/'),
            "English route [{$name}.en] should live under /en, got [{$english}]."
        );
    }

    #[DataProvider('publicRoutes')]
    public function test_both_variants_respond_and_set_the_matching_locale(string $name): void
    {
        $this->seed();

        $this->get(route($name))->assertOk()->assertSee('<html lang="ar" dir="rtl">', false);
        $this->get(route("{$name}.en"))->assertOk()->assertSee('<html lang="en" dir="ltr">', false);
    }

    public function test_lroute_resolves_to_the_variant_for_the_current_locale(): void
    {
        app()->setLocale('ar');
        $this->assertSame(route('pages.about'), lroute('pages.about'));

        app()->setLocale('en');
        $this->assertSame(route('pages.about.en'), lroute('pages.about'));
    }

    public function test_lroute_falls_through_untouched_for_single_locale_routes(): void
    {
        Route::get('/_test-single', fn () => 'ok')->name('test.single');
        // A route added after boot is not in the name lookup table until
        // it is rebuilt, and route() resolves through that table.
        Route::getRoutes()->refreshNameLookups();

        app()->setLocale('en');

        // No ".en" variant exists, so it must return the plain route
        // rather than throwing — this is what makes lroute() safe to use
        // in a layout shared with non-public pages.
        $this->assertSame(route('test.single'), lroute('test.single'));
    }

    public function test_current_route_base_name_strips_the_locale_suffix(): void
    {
        $this->seed();

        $this->get('/about');
        $this->assertSame('pages.about', current_route_base_name());

        $this->get('/en/about');
        $this->assertSame('pages.about', current_route_base_name());
    }

    public function test_route_in_locale_preserves_the_current_page(): void
    {
        $this->seed();

        $this->get('/about');
        $this->assertSame(route('pages.about.en'), route_in_locale('en'));
        $this->assertSame(route('pages.about'), route_in_locale('ar'));

        $this->get('/en/about');
        $this->assertSame(route('pages.about'), route_in_locale('ar'));
    }

    public function test_route_in_locale_returns_null_off_the_dual_registered_routes(): void
    {
        Route::middleware('web')->get('/_test-solo', fn () => response(route_in_locale('en') ?? 'null'))->name('test.solo');

        // A page with no English variant must emit no hreflang rather than
        // pointing at a URL that does not exist.
        $this->get('/_test-solo')->assertSee('null');
    }

    public function test_an_unknown_url_is_a_404_in_both_locales(): void
    {
        $this->get('/no-such-page')->assertNotFound();
        $this->get('/en/no-such-page')->assertNotFound();
    }

    public function test_security_headers_are_present_on_public_pages_and_on_errors(): void
    {
        $this->seed();

        foreach (['/', '/en', '/no-such-page'] as $url) {
            $response = $this->get($url);

            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $this->assertNotNull(
                $response->headers->get('Content-Security-Policy-Report-Only'),
                "No CSP on [{$url}] — exception responses bypass the middleware unless the handler re-applies it."
            );
        }
    }
}
