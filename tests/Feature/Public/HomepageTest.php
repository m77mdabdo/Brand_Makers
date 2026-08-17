<?php

namespace Tests\Feature\Public;

use App\Models\Article;
use App\Models\EstimatorItem;
use App\Models\Faq;
use App\Models\Package;
use App\Models\Service;
use App\Models\Testimonial;
use App\Services\Cms\SettingsRepository;
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

        /*
         * Read from the settings rather than hardcoded.
         *
         * This test used to pin the literal branding title, so a content
         * change broke it for a reason that had nothing to do with what
         * it guards. What it actually guards is that the homepage uses
         * home_meta_title AT ALL — the homepage has no backing record to
         * carry a SeoMeta row, so without the home_seo settings it falls
         * through to the generic site default, which is the classic way
         * the most-shared page on a site ends up with a placeholder
         * title. Asserting the two differ is what proves it did not.
         */
        // translated() reads the CURRENT locale, and the test process
        // starts in Arabic regardless of which URL is about to be
        // requested — so the locale has to be set before reading, or the
        // English case compares the page against the Arabic title.
        $this->app->setLocale($locale);

        $settings = app(SettingsRepository::class);

        $home = $settings->translated('home_meta_title');
        $default = $settings->translated('default_meta_title');

        $this->assertNotSame($default, $home, 'The homepage title must not be the site-wide default.');

        $this->get($url)->assertSee('<title>'.e($home).'</title>', false);
    }

    // -----------------------------------------------------------------
    // The estimator teaser's range
    // -----------------------------------------------------------------

    public function test_the_teaser_range_spans_packages_as_well_as_items(): void
    {
        $this->seed();

        // A fresh install prices the three tiers and ships the add-on
        // catalogue unpriced. Reading items alone made the teaser announce
        // "pricing in preparation" on the same page whose packages section
        // was quoting 13,000 — the site contradicting itself.
        $this->get('/')
            ->assertSee('13,000', false)
            ->assertSee('30,000', false)
            ->assertDontSee(__('site.home_estimator.unpriced'), false);
    }

    public function test_a_priced_add_on_widens_the_range(): void
    {
        $this->seed();

        EstimatorItem::query()->where('key', 'bank-account')->update(['price' => 4500, 'is_active' => true]);

        $this->get('/')->assertSee('4,500', false)->assertSee('30,000', false);
    }

    public function test_a_single_priced_row_renders_one_figure_rather_than_a_range_of_one(): void
    {
        $this->seed();

        Package::query()->update(['price' => null]);
        EstimatorItem::query()->where('key', 'bank-account')->update(['price' => 4500, 'is_active' => true]);

        $body = $this->get('/')->assertOk()->getContent();

        preg_match('/<p class="mt-1 text-3xl font-black text-amber.*?<\/p>/s', $body, $figure);
        $this->assertNotEmpty($figure);

        // This rendered "1 – 1" when min and max were the same row.
        $this->assertStringContainsString('4,500', $figure[0]);
        $this->assertStringNotContainsString('–', $figure[0], 'A span of one number is not a range.');
    }

    public function test_an_activated_but_unpriced_item_does_not_advertise_a_minimum_of_zero(): void
    {
        $this->seed();

        // The catalogue ships at 0.00 and inactive because 0 is not a
        // price. An admin who activates a row before pricing it must not
        // drag the advertised minimum to zero.
        EstimatorItem::query()->where('key', 'bank-account')->update(['is_active' => true]);

        $this->get('/')->assertSee('13,000', false)->assertDontSee('>0 –', false);
    }

    public function test_nothing_priced_hides_the_range_cleanly(): void
    {
        $this->seed();

        Package::query()->update(['price' => null]);

        $this->get('/')
            ->assertSee(__('site.home_estimator.unpriced'), false)
            ->assertDontSee(__('site.home_estimator.total_label'), false);
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

    /**
     * This used to assert the opposite — that a seeded install quotes no
     * number, because the catalogue ships inactive. That was true while
     * the range read estimator items ALONE, and it is what let the teaser
     * announce "pricing in preparation" on a page already quoting 13,000
     * in its packages section. The seeded install has three real prices,
     * so quoting them is correct; the "nothing priced" case is asserted
     * in test_nothing_priced_hides_the_range_cleanly instead.
     */
    public function test_the_estimator_teaser_shows_a_real_range_once_anything_is_priced(): void
    {
        $this->seed();

        EstimatorItem::factory()->create(['key' => 'cheap', 'price' => 1200, 'is_active' => true]);
        EstimatorItem::factory()->inactive()->create(['key' => 'hidden', 'price' => 99999]);

        $response = $this->get('/');

        $response->assertSee('1,200', false);
        // The dearest package, since the span covers both sources.
        $response->assertSee('30,000', false);
        // An inactive item must not stretch the published range.
        $response->assertDontSee('99,999', false);
    }

    /**
     * price = null means "on request", which is a different fact from
     * 0.00 (free). The seeded tiers all carry a real figure now, so the
     * unpriced case is built explicitly rather than borrowed from the
     * seed — which is what stops this test quietly passing for the wrong
     * reason the next time pricing changes.
     */
    public function test_a_package_with_no_price_reads_as_on_request_not_as_free(): void
    {
        $this->seed();

        Package::factory()->create([
            'price' => null,
            'sort_order' => 99,
            'name' => ['ar' => 'باقة مخصّصة', 'en' => 'Custom package'],
        ]);

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
