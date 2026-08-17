<?php

namespace Tests\Feature\Content;

use App\Models\Article;
use App\Models\Country;
use App\Models\EstimatorItem;
use App\Models\Faq;
use App\Models\Package;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What a fresh `migrate:fresh --seed` actually ships.
 *
 * This site was seeded as a branding studio and redirected to a company
 * formation and investment consultancy. These tests hold that redirect in
 * place: they assert the content is present, complete in both languages,
 * reachable, and free of the vocabulary it replaced.
 *
 * The most load-bearing test here is the translation-completeness one.
 * The seeders write Arabic only and content:translate-to-english fills
 * the English from its dictionary, which means new Arabic copy with no
 * dictionary entry ships an EMPTY English field — spatie falls back to
 * the Arabic, so the page still renders and nothing looks broken. That
 * test is what turns a silent hole into a failing build.
 */
class ContentRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        return ['Arabic' => ['ar'], 'English' => ['en']];
    }

    // -----------------------------------------------------------------
    // Both languages, everywhere
    // -----------------------------------------------------------------

    /**
     * Every seeded model class whose copy has to exist in both languages.
     *
     * @return array<string, array{class-string<Model>}>
     */
    public static function translatedModels(): array
    {
        return [
            'Page' => [Page::class],
            'Service' => [Service::class],
            'Package' => [Package::class],
            'EstimatorItem' => [EstimatorItem::class],
            'Country' => [Country::class],
            'Faq' => [Faq::class],
            'Article' => [Article::class],
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('translatedModels')]
    public function test_every_seeded_record_carries_both_languages(string $modelClass): void
    {
        $records = $modelClass::all();

        $this->assertNotEmpty($records, $modelClass.' seeded nothing.');

        $gaps = [];

        foreach ($records as $record) {
            /*
             * Read the fields off the MODEL's own $translatable rather
             * than a list in this file. A field added to a model is then
             * covered here automatically, which a hardcoded list would
             * quietly miss — and quietly missing is the whole failure
             * mode being guarded against.
             */
            foreach ($record->getTranslatableAttributes() as $field) {
                // false: raw, no fallback. With the fallback on, an empty
                // English field reads back as the Arabic and every one of
                // these assertions passes for the wrong reason.
                $arabic = $record->getTranslation($field, 'ar', false);

                if (blank($arabic)) {
                    continue;
                }

                if (blank($record->getTranslation($field, 'en', false))) {
                    $gaps[] = class_basename($modelClass)."#{$record->getKey()} [{$field}]";
                }
            }
        }

        $this->assertSame([], $gaps, 'Arabic content with no English. Add a dictionary entry to content:translate-to-english for: '.implode(', ', $gaps));
    }

    public function test_every_page_section_carries_both_languages(): void
    {
        $sections = PageSection::all();

        $this->assertNotEmpty($sections);

        foreach ($sections as $section) {
            foreach (['title', 'description'] as $field) {
                $this->assertNotEmpty(
                    $section->content[$field]['en'] ?? null,
                    "PageSection#{$section->getKey()} [{$field}] has no English."
                );
            }
        }
    }

    public function test_every_package_feature_carries_both_languages(): void
    {
        foreach (Package::all() as $package) {
            foreach ($package->features ?? [] as $index => $feature) {
                $this->assertNotEmpty(
                    $feature['en'] ?? null,
                    "Package#{$package->getKey()} feature {$index} has no English."
                );
            }
        }
    }

    // -----------------------------------------------------------------
    // The catalogue itself
    // -----------------------------------------------------------------

    public function test_the_service_catalogue_is_the_eight_formation_services(): void
    {
        $this->assertSame([
            'llc-formation',
            'foreign-investment-licence',
            'company-acquisition',
            'commercial-registration',
            'national-address-bank-account',
            'municipal-sector-licences',
            'post-formation-services',
            'consulting-and-studies',
        ], Service::active()->ordered()->pluck('slug')->all());
    }

    public function test_the_branding_catalogue_is_gone_from_every_table(): void
    {
        // withTrashed: a soft-deleted service would still be restorable
        // from the dashboard's trash, which is not "gone".
        $this->assertSame(0, Service::withTrashed()->whereIn('slug', [
            'brand-strategy', 'visual-identity', 'logo-design', 'motion-graphics',
            'print-collateral', 'packaging-design', 'social-media-design', 'web-ui-design',
        ])->count());

        $this->assertSame(0, Article::withTrashed()->whereIn('slug', [
            'brand-is-not-a-logo', 'arabic-typography-in-branding',
            'what-to-prepare-before-a-branding-project',
        ])->count());

        $this->assertSame(0, EstimatorItem::whereIn('key', [
            'logo-design', 'brand-guidelines', 'packaging', 'animated-logo',
        ])->count());

        // Packages are soft-deleted rather than force-deleted, because
        // orders and leads carry a package_id — so "gone" here means gone
        // from the public site, not gone from the table.
        $this->assertSame(0, Package::active()->whereIn('slug', ['starter', 'growth', 'complete', 'enterprise'])->count());
    }

    /**
     * A regression guard on the redirect itself.
     *
     * Deliberately phrase-level, not word-level: the COMPANY is called
     * صنّاع العلامات التجارية / Creative Mark, so "brand" and "علامة" are
     * legitimate and appear in the site name on every page. What must not
     * come back is the vocabulary of the service catalogue.
     */
    #[DataProvider('locales')]
    public function test_no_page_advertises_the_branding_services_it_no_longer_offers(string $locale): void
    {
        $retired = $locale === 'ar'
            ? ['الهوية البصرية', 'تصميم الشعار', 'دليل الهوية', 'استوديو']
            : ['visual identity', 'logo design', 'brand strategy', 'brand guide'];

        $prefix = $locale === 'ar' ? '' : '/en';

        foreach (['', '/services', '/packages', '/activities', '/why-saudi', '/about', '/articles', '/faqs'] as $path) {
            $body = $this->get($prefix.($path ?: '/'))->assertOk()->getContent();

            foreach ($retired as $phrase) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $phrase,
                    $body,
                    "\"{$phrase}\" still appears on {$prefix}{$path}."
                );
            }
        }
    }

    // -----------------------------------------------------------------
    // Rendering, in both locales
    // -----------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_the_services_page_lists_every_service(string $locale): void
    {
        $prefix = $locale === 'ar' ? '' : '/en';

        $response = $this->get($prefix.'/services')->assertOk();

        foreach (Service::active()->get() as $service) {
            $response->assertSee($service->getTranslation('name', $locale), false);
        }
    }

    #[DataProvider('locales')]
    public function test_the_packages_page_quotes_the_real_prices(string $locale): void
    {
        $prefix = $locale === 'ar' ? '' : '/en';

        $response = $this->get($prefix.'/packages')->assertOk();

        foreach (['13,000', '22,000', '30,000'] as $price) {
            $response->assertSee($price, false);
        }

        $response->assertSee(__('site.packages.popular', [], $locale), false);
    }

    #[DataProvider('locales')]
    public function test_the_four_activities_render_on_the_homepage_and_on_their_own_page(string $locale): void
    {
        $prefix = $locale === 'ar' ? '' : '/en';

        $page = Page::where('slug', 'activities')->firstOrFail();
        $titles = $page->sections->map(fn (PageSection $s) => $s->content['title'][$locale]);

        $this->assertCount(4, $titles);

        foreach ([$prefix.'/', $prefix.'/activities'] as $url) {
            $response = $this->get($url)->assertOk();

            foreach ($titles as $title) {
                $response->assertSee($title, false);
            }
        }
    }

    #[DataProvider('locales')]
    public function test_the_why_saudi_reasons_render_on_the_homepage_and_on_their_own_page(string $locale): void
    {
        $prefix = $locale === 'ar' ? '' : '/en';

        $page = Page::where('slug', 'why-invest-saudi-arabia')->firstOrFail();
        $titles = $page->sections->map(fn (PageSection $s) => $s->content['title'][$locale]);

        $this->assertCount(5, $titles);

        foreach ([$prefix.'/', $prefix.'/why-saudi'] as $url) {
            $response = $this->get($url)->assertOk();

            foreach ($titles as $title) {
                $response->assertSee($title, false);
            }
        }
    }

    #[DataProvider('locales')]
    public function test_the_homepage_carries_the_four_value_props(string $locale): void
    {
        $prefix = $locale === 'ar' ? '' : '/en';

        $response = $this->get($prefix.'/')->assertOk();

        foreach (__('site.values.items', [], $locale) as $value) {
            $response->assertSee($value['title'], false);
        }
    }

    #[DataProvider('locales')]
    public function test_the_how_we_work_page_reads_as_four_formation_stages(string $locale): void
    {
        $prefix = $locale === 'ar' ? '' : '/en';

        $page = Page::where('slug', 'our-process')->firstOrFail();

        $this->assertCount(4, $page->sections);

        $response = $this->get($prefix.'/our-process')->assertOk();

        foreach ($page->sections as $section) {
            $response->assertSee($section->content['title'][$locale], false);
        }
    }

    /**
     * Reported as "English package names are missing — slugs like
     * formation-basic show instead".
     *
     * It does not reproduce on a seeded database, and it cannot: spatie
     * falls back to the Arabic when an English translation is absent, so
     * the degraded state shows Arabic, never a slug. Reproduced the
     * pre-translation state deliberately (run PackageSeeder without the
     * translate step) and confirmed /en/packages rendered Arabic names and
     * zero slugs in visible text.
     *
     * The likely cause is a database seeded before the translate step
     * existed. These assertions are what would have caught it either way:
     * the English name has to be the ENGLISH one, not the Arabic fallback,
     * and no slug may reach the page as text.
     */
    public function test_the_english_packages_page_shows_english_names_not_arabic_and_not_slugs(): void
    {
        $body = $this->get('/en/packages')->assertOk()->getContent();

        foreach (Package::active()->get() as $package) {
            $english = $package->getTranslation('name', 'en', false);
            $arabic = $package->getTranslation('name', 'ar', false);

            $this->assertNotEmpty($english, "Package {$package->slug} has no English name.");
            $this->assertStringContainsString($english, $body);
            $this->assertStringNotContainsString($arabic, $body, 'The English page fell back to Arabic.');
        }

        // The slug is an internal identifier and a URL segment. It is
        // rendered deliberately in the DASHBOARD list as a muted hint, and
        // must never reach a public page as visible text.
        $visible = strip_tags($body);

        foreach (Package::withTrashed()->pluck('slug') as $slug) {
            $this->assertStringNotContainsString($slug, $visible, "The slug \"{$slug}\" is visible on /en/packages.");
        }
    }

    public function test_the_english_packages_page_shows_english_feature_bullets(): void
    {
        $body = $this->get('/en/packages')->assertOk()->getContent();

        foreach (Package::active()->get() as $package) {
            foreach ($package->features ?? [] as $feature) {
                $this->assertNotEmpty($feature['en'] ?? null);
                $this->assertStringContainsString($feature['en'], $body);
            }
        }
    }

    public function test_the_dictionary_covers_every_package_string(): void
    {
        // Re-running the translator over already-translated content must
        // report nothing missing. --force makes it retranslate rather than
        // skip, which is what turns this into a coverage check instead of
        // a no-op.
        $this->artisan('content:translate-to-english', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('English translation summary');

        foreach (Package::all() as $package) {
            $this->assertNotEmpty($package->getTranslation('name', 'en', false));
            $this->assertNotEmpty($package->getTranslation('description', 'en', false));

            foreach ($package->features ?? [] as $feature) {
                $this->assertNotEmpty($feature['en'] ?? null);
            }
        }
    }

    // -----------------------------------------------------------------
    // The funnel
    // -----------------------------------------------------------------

    /**
     * home → services / activities → packages / estimator → book.
     *
     * Every one of these was a homepage fragment before the redirect, and
     * three pointed at the wrong section entirely, so this walks the real
     * hrefs rather than trusting the markup to still say what it said.
     */
    #[DataProvider('locales')]
    public function test_the_homepage_offers_the_whole_journey_as_real_links(string $locale): void
    {
        $prefix = $locale === 'ar' ? '' : '/en';
        $home = $prefix ?: '/';

        $body = $this->get($home)->assertOk()->getContent();

        foreach (['/services', '/packages', '/activities', '/cost-estimator', '/book', '/why-saudi'] as $path) {
            $this->assertStringContainsString(
                'href="'.config('app.url').$prefix.$path,
                $body,
                "The homepage does not link to {$prefix}{$path}."
            );
        }

        // And no leftover fragment CTA: those did nothing anywhere but here.
        $this->assertStringNotContainsString('href="#booking"', $body);
        $this->assertStringNotContainsString('href="#estimator"', $body);
    }

    #[DataProvider('locales')]
    public function test_every_destination_in_that_journey_is_reachable(string $locale): void
    {
        $prefix = $locale === 'ar' ? '' : '/en';

        foreach (['/services', '/packages', '/activities', '/why-saudi', '/cost-estimator', '/book', '/about', '/our-process'] as $path) {
            $this->get($prefix.$path)->assertOk();
        }
    }
}
