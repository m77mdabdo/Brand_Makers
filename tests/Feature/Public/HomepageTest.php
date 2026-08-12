<?php

namespace Tests\Feature\Public;

use App\Models\Article;
use App\Models\EstimatorItem;
use App\Models\Faq;
use App\Models\Package;
use App\Models\Service;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function locales(): array
    {
        return [
            'Arabic (no prefix)' => ['/', 'ar', 'rtl'],
            'English (/en)' => ['/en', 'en', 'ltr'],
        ];
    }

    #[DataProvider('locales')]
    public function test_the_homepage_renders_in_the_right_language_and_direction(string $url, string $locale, string $dir): void
    {
        $this->seed();

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('<html lang="'.$locale.'" dir="'.$dir.'">', false);
    }

    #[DataProvider('locales')]
    public function test_the_homepage_renders_every_section_in_order(string $url): void
    {
        $this->seed();

        $response = $this->get($url);

        // The agreed flow. assertSeeInOrder is what makes this a test of
        // the PAGE rather than of a set of unrelated fragments.
        $response->assertSeeInOrder([
            'data-hero',
            'cm-marquee',
            'id="services"',
            'id="estimator"',
            'id="packages"',
            'id="tracker"',
            'id="booking"',
            '<footer',
        ], false);
    }

    #[DataProvider('locales')]
    public function test_the_homepage_pulls_its_content_from_the_database(string $url, string $locale): void
    {
        $this->seed();

        $response = $this->get($url);

        $service = Service::active()->ordered()->first();
        $package = Package::active()->ordered()->first();
        $faq = Faq::active()->ordered()->first();
        $article = Article::live()->latest('published_at')->first();

        $response->assertSee($service->getTranslation('name', $locale), false);
        $response->assertSee($package->getTranslation('name', $locale), false);
        $response->assertSee($faq->getTranslation('question', $locale), false);
        $response->assertSee($article->getTranslation('title', $locale), false);
    }

    #[DataProvider('locales')]
    public function test_the_homepage_emits_canonical_hreflang_and_social_tags(string $url, string $locale): void
    {
        $this->seed();

        $response = $this->get($url);

        $response->assertSee('rel="canonical" href="'.config('app.url').($url === '/' ? '' : $url).'"', false);

        // Both alternates on both locales, plus x-default on the Arabic
        // (no-prefix) URL — a page that advertises only its own language
        // gives a crawler no way to find the other one.
        $response->assertSee('hreflang="ar"', false);
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('hreflang="x-default"', false);

        $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
        $response->assertSee('og-image.jpg', false);
        $response->assertSee('content="'.($locale === 'ar' ? 'ar_SA' : 'en_US').'"', false);
    }

    #[DataProvider('locales')]
    public function test_the_homepage_wires_the_favicons(string $url): void
    {
        $this->seed();

        $response = $this->get($url);

        $response->assertSee('rel="icon" href="'.config('app.url').'/favicon.ico" sizes="any"', false);
        $response->assertSee('rel="icon" type="image/png" href="'.config('app.url').'/favicon-32.png" sizes="32x32"', false);
        $response->assertSee('rel="apple-touch-icon" href="'.config('app.url').'/apple-touch-icon.png"', false);
    }

    #[DataProvider('locales')]
    public function test_the_layout_uses_the_dark_logo_in_the_navbar_and_the_light_one_in_the_footer(string $url): void
    {
        $this->seed();

        $response = $this->get($url);

        $response->assertSee('logo-dark.png', false);
        $response->assertSee('logo-light.png', false);
    }

    #[DataProvider('locales')]
    public function test_the_homepage_uses_its_own_seo_settings_not_the_site_default(string $url, string $locale): void
    {
        $this->seed();

        $expected = $locale === 'ar'
            ? 'صنّاع العلامات التجارية — تصميم الهوية البصرية واستراتيجية العلامة'
            : 'Creative Mark — Visual Identity &amp; Brand Strategy';

        // The homepage has no backing record to carry a SeoMeta row, so
        // without the home_seo settings it would silently fall through to
        // the generic site default — the classic way the most-shared page
        // on a site ends up with a placeholder title.
        $this->get($url)->assertSee('<title>'.$expected.'</title>', false);
    }

    // -----------------------------------------------------------------
    // The animated hero
    // -----------------------------------------------------------------

    public function test_the_hero_renders_every_animated_layer(): void
    {
        $this->seed();

        $response = $this->get('/');

        $response->assertSee('data-hero', false);
        $response->assertSee('data-hero-particles', false);
        $response->assertSee('animate-sun-breathe', false);
        $response->assertSee('animate-cloud-slow', false);
        $response->assertSee('animate-cloud-fast', false);
        $response->assertSee('animate-twinkle', false);

        // Two skyline layers at DIFFERENT rates — one depth value would be
        // a single layer wearing a parallax attribute.
        $response->assertSee('data-parallax-depth="0.04"', false);
        $response->assertSee('data-parallax-depth="0.09"', false);
    }

    public function test_the_hero_window_lights_are_stable_across_renders(): void
    {
        $this->seed();

        // Seeded, not random-per-request: an unstable skyline would differ
        // between the two locales' renders of the same page and defeat
        // HTTP caching for no benefit.
        $first = $this->get('/')->getContent();
        $second = $this->get('/')->getContent();

        // \s+ rather than a literal space: Blade renders each attribute
        // on its own line, so a single-space pattern matches nothing.
        $pattern = '/class="animate-twinkle"\s+x="(\d+)"\s+y="(\d+)"/';

        preg_match_all($pattern, $first, $firstWindows);
        preg_match_all($pattern, $second, $secondWindows);

        $this->assertNotEmpty($firstWindows[0], 'The skyline should have lit windows.');
        $this->assertSame($firstWindows[0], $secondWindows[0]);
    }

    public function test_the_hero_background_is_swappable_for_a_video_without_touching_the_layout(): void
    {
        $this->seed();

        // The generated sky by default...
        $this->get('/')->assertSee('data-hero-particles', false);

        // ...and a video when one is supplied, with the SAME hero box and
        // the SAME readability overlay around it. This is the property
        // that makes swapping in real footage a data change later.
        $rendered = $this->blade('<x-public.hero video="/video/skyline.mp4" poster="/img/poster.jpg" />');

        $rendered->assertSee('<source src="/video/skyline.mp4" type="video/mp4">', false);
        $rendered->assertSee('poster="/img/poster.jpg"', false);
        $rendered->assertSee('playsinline', false);
        $rendered->assertDontSee('data-hero-particles', false);
        // The box and the overlay are unchanged.
        $rendered->assertSee('min-h-[88svh]', false);
        $rendered->assertSee('rgba(8,30,43,0.62)', false);
    }

    // -----------------------------------------------------------------
    // Marquee
    // -----------------------------------------------------------------

    /**
     * Adjacent bands must shear PAST each other, and the whole pattern has
     * to flip with the locale — otherwise the effect that reads correctly
     * in Arabic reads backwards in English.
     */
    #[DataProvider('marqueeDirections')]
    public function test_the_marquees_run_in_opposite_directions_per_locale(string $url, string $first, string $second): void
    {
        $this->seed();

        $content = $this->get($url)->getContent();

        preg_match_all('/data-marquee-direction="(\w+)"/', $content, $matches);

        $this->assertSame([$first, $second], $matches[1]);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function marqueeDirections(): array
    {
        return [
            'Arabic: base is rtl, reverse is ltr' => ['/', 'rtl', 'ltr'],
            'English: base is ltr, reverse is rtl' => ['/en', 'ltr', 'rtl'],
        ];
    }

    public function test_the_marquee_is_hidden_from_assistive_tech(): void
    {
        $this->seed();

        // It repeats text that already appears as real headings further
        // down the page, so announcing it twice is noise.
        $content = $this->get('/')->getContent();

        $this->assertMatchesRegularExpression('/<div\s+class="cm-marquee[^"]*"[^>]*aria-hidden="true"/s', $content);
    }

    // -----------------------------------------------------------------
    // Empty and partial states
    // -----------------------------------------------------------------

    public function test_a_section_with_no_data_is_not_rendered_at_all(): void
    {
        // No seeding: an empty database must not produce headings with
        // nothing under them.
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('id="services"', false);
        $response->assertDontSee('id="packages"', false);
        $response->assertDontSee('id="faq"', false);
        $response->assertDontSee('cm-marquee', false);

        // The static sections still render — they carry no data.
        $response->assertSee('id="booking"', false);
        $response->assertSee('id="tracker"', false);
    }

    public function test_the_production_seed_shows_no_testimonials_section(): void
    {
        $this->seed();

        // Testimonials are demo-only data, so an empty testimonials
        // section IS the correct production state.
        $this->assertSame(0, Testimonial::count());
        $this->get('/')->assertDontSee('id="testimonials"', false);
    }

    public function test_the_testimonials_section_appears_once_there_are_testimonials(): void
    {
        $this->seed();
        Testimonial::factory()->create(['quote' => ['ar' => 'شهادة تجريبية', 'en' => 'A test quote']]);

        $this->get('/')->assertSee('id="testimonials"', false);
    }

    public function test_the_estimator_teaser_quotes_no_number_until_items_are_priced(): void
    {
        $this->seed();

        // The production seed ships the estimator catalogue inactive on
        // purpose — a teaser that invented a figure would quote visitors a
        // price the studio never agreed.
        $this->assertSame(0, EstimatorItem::active()->count());

        $response = $this->get('/');
        $response->assertSee(__('site.estimator.unpriced'), false);
        $response->assertDontSee(__('site.estimator.total_label'), false);
    }

    public function test_the_estimator_teaser_shows_a_real_range_once_items_are_priced(): void
    {
        $this->seed();

        EstimatorItem::factory()->create(['key' => 'cheap', 'price' => 1200, 'is_active' => true]);
        EstimatorItem::factory()->create(['key' => 'dear', 'price' => 18000, 'is_active' => true]);
        EstimatorItem::factory()->inactive()->create(['key' => 'hidden', 'price' => 99999]);

        $response = $this->get('/');

        $response->assertSee('1,200', false);
        $response->assertSee('18,000', false);
        // An inactive item must not stretch the published range.
        $response->assertDontSee('99,999', false);
    }

    public function test_a_package_with_no_price_reads_as_on_request_not_as_free(): void
    {
        $this->seed();

        // Every seeded package ships price = null.
        $this->assertSame(Package::count(), Package::whereNull('price')->count());

        $response = $this->get('/');
        $response->assertSee(__('site.packages.price_on_request'), false);
        $response->assertDontSee('>0<', false);
    }

    public function test_a_priced_package_shows_its_figure(): void
    {
        $this->seed();
        Package::factory()->create(['price' => 24500, 'sort_order' => 99]);

        $this->get('/')->assertSee('24,500', false);
    }

    // -----------------------------------------------------------------
    // Chrome
    // -----------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_the_page_has_a_skip_link_and_a_back_to_top_button(string $url): void
    {
        $this->seed();

        $response = $this->get($url);

        $response->assertSee('href="#main-content"', false);
        $response->assertSee(__('site.nav.skip_to_content'), false);
        $response->assertSee(__('site.nav.back_to_top'), false);
        $response->assertSee('id="main-content"', false);
    }

    #[DataProvider('locales')]
    public function test_the_navbar_is_translucent_and_blurred(string $url): void
    {
        $this->seed();

        $response = $this->get($url);

        // The blur is the enhancement; the higher-opacity bar is the
        // fallback, and both have to be present or the links become
        // unreadable over the hero on browsers without backdrop-filter.
        $response->assertSee('bg-cream/90', false);
        $response->assertSee('supports-[backdrop-filter]:backdrop-blur-xl', false);
    }

    public function test_the_language_toggle_points_at_the_same_page_in_the_other_locale(): void
    {
        $this->seed();

        $this->get('/')->assertSee('href="'.config('app.url').'/en"', false);
        $this->get('/en')->assertSee('href="'.config('app.url').'"', false);
    }

    public function test_the_navbar_drops_menu_items_whose_route_does_not_exist_yet(): void
    {
        $this->seed();

        $content = $this->get('/')->getContent();

        // Nothing may render a link to a route that is not registered —
        // an entry for a page that does not exist yet is invisible, not a
        // 404 waiting to happen.
        $this->assertStringNotContainsString('href=""', $content);
        $this->assertStringNotContainsString('href="#"', $content);
    }

    public function test_menu_items_without_a_dedicated_page_link_to_the_homepage_section_in_the_current_locale(): void
    {
        $this->seed();

        // Arabic homepage anchors have no prefix...
        $this->get('/')->assertSee('href="'.config('app.url').'#packages"', false);
        // ...and the English ones stay inside /en.
        $this->get('/en')->assertSee('href="'.config('app.url').'/en#packages"', false);
    }
}
