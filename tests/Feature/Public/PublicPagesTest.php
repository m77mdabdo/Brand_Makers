<?php

namespace Tests\Feature\Public;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Country;
use App\Models\Faq;
use App\Models\Package;
use App\Models\Service;
use App\Models\Setting;
use App\Services\Cms\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * Every parameter-free public page, by route name.
     *
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'home' => ['home'],
            'services index' => ['services.index'],
            'packages' => ['packages.index'],
            'countries' => ['countries.index'],
            'faqs' => ['faqs.index'],
            'articles index' => ['articles.index'],
            'contact' => ['contact'],
            'about' => ['pages.about'],
            'our process' => ['pages.our-process'],
            'privacy policy' => ['pages.privacy-policy'],
            'terms' => ['pages.terms-and-conditions'],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_page_responds_in_both_locales_with_the_right_direction(string $route): void
    {
        $this->get(route($route))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false);

        $this->get(route($route.'.en'))
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false);
    }

    #[DataProvider('pages')]
    public function test_the_page_emits_a_title_a_canonical_and_both_hreflangs(string $route): void
    {
        foreach ([route($route), route($route.'.en')] as $url) {
            $response = $this->get($url);

            // Never empty: SeoTagService falls through the record's own
            // SeoMeta row, then the view's value, then the record's
            // content, then the site defaults.
            $this->assertMatchesRegularExpression('/<title>\s*\S.*<\/title>/', $response->getContent());

            $response->assertSee('rel="canonical" href="'.$url.'"', false);
            $response->assertSee('hreflang="ar"', false);
            $response->assertSee('hreflang="en"', false);
            $response->assertSee('hreflang="x-default"', false);
            $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
        }
    }

    #[DataProvider('pages')]
    public function test_the_page_carries_the_chrome(string $route): void
    {
        $response = $this->get(route($route));

        $response->assertSee('href="#main-content"', false);
        $response->assertSee(__('site.nav.back_to_top'), false);
        $response->assertSee('logo-dark.png', false);
        $response->assertSee('logo-light.png', false);
    }

    // -----------------------------------------------------------------
    // Services
    // -----------------------------------------------------------------

    public function test_the_services_index_lists_active_services_in_order(): void
    {
        $response = $this->get(route('services.index'));

        foreach (Service::active()->ordered()->get() as $service) {
            $response->assertSee($service->getTranslation('name', 'ar'), false);
        }
    }

    public function test_an_inactive_service_is_hidden_from_the_index_and_404s_on_its_own_url(): void
    {
        $service = Service::factory()->inactive()->create([
            'name' => ['ar' => 'خدمة معطّلة', 'en' => 'Inactive service'],
            'slug' => 'inactive-service',
        ]);

        $this->get(route('services.index'))->assertDontSee('خدمة معطّلة', false);

        // Route model binding resolves by slug with NO active scope, so
        // without abort_unless the URL would stay publicly readable.
        $this->get(route('services.show', $service))->assertNotFound();
        $this->get(route('services.show.en', $service))->assertNotFound();
    }

    public function test_a_service_page_shows_its_body_requirements_and_process(): void
    {
        $service = Service::active()->ordered()->first();

        $this->get(route('services.show', $service))
            ->assertOk()
            ->assertSee($service->getTranslation('name', 'ar'), false)
            ->assertSee(__('site.services.requirements'), false)
            ->assertSee(__('site.services.process'), false);
    }

    // -----------------------------------------------------------------
    // Countries and FAQs
    // -----------------------------------------------------------------

    public function test_inactive_countries_and_faqs_are_excluded(): void
    {
        Country::factory()->inactive()->create(['name' => ['ar' => 'دولة معطّلة', 'en' => 'Inactive country']]);
        Faq::factory()->inactive()->create(['question' => ['ar' => 'سؤال معطّل', 'en' => 'Inactive question']]);

        $this->get(route('countries.index'))->assertOk()->assertDontSee('دولة معطّلة', false);
        $this->get(route('faqs.index'))->assertOk()->assertDontSee('سؤال معطّل', false);
    }

    public function test_the_countries_and_faq_indexes_list_their_active_records(): void
    {
        $this->get(route('countries.index'))->assertSee(Country::active()->ordered()->first()->getTranslation('name', 'ar'), false);
        $this->get(route('faqs.index'))->assertSee(Faq::active()->ordered()->first()->getTranslation('question', 'ar'), false);
    }

    // -----------------------------------------------------------------
    // Articles
    // -----------------------------------------------------------------

    public function test_the_articles_index_shows_only_published_and_due_posts(): void
    {
        $draft = Article::factory()->draft()->create(['title' => ['ar' => 'مسودة', 'en' => 'Draft']]);
        $scheduled = Article::factory()->scheduled()->create(['title' => ['ar' => 'مجدول', 'en' => 'Scheduled']]);

        $response = $this->get(route('articles.index'));

        $response->assertOk();
        $response->assertDontSee('مسودة', false);
        $response->assertDontSee('مجدول', false);

        // …and neither is readable by URL either.
        $this->get(route('articles.show', $draft))->assertNotFound();
        $this->get(route('articles.show', $scheduled))->assertNotFound();
    }

    public function test_a_scheduled_article_becomes_visible_once_its_date_passes(): void
    {
        $article = Article::factory()->scheduled()->create(['title' => ['ar' => 'مجدول', 'en' => 'Scheduled']]);

        $this->get(route('articles.index'))->assertDontSee('مجدول', false);

        // No cron job: the scope filters on published_at <= now(), so the
        // post goes live purely because time passed.
        $this->travel(8)->days();

        $this->get(route('articles.index'))->assertSee('مجدول', false);
        $this->get(route('articles.show', $article))->assertOk();
    }

    public function test_an_article_page_shows_only_approved_comments_oldest_first(): void
    {
        $article = Article::live()->first();

        $approvedSecond = Comment::factory()->for($article)->approved()->create([
            'name' => 'Second Commenter',
            'created_at' => now()->subDay(),
        ]);
        $approvedFirst = Comment::factory()->for($article)->approved()->create([
            'name' => 'First Commenter',
            'created_at' => now()->subWeek(),
        ]);
        Comment::factory()->for($article)->create(['name' => 'Pending Commenter']);
        Comment::factory()->for($article)->rejected()->create(['name' => 'Rejected Commenter']);

        $response = $this->get(route('articles.show', $article));

        $response->assertOk();

        // A comment awaiting moderation has been published by nobody;
        // showing it would make the moderation queue decorative.
        $response->assertDontSee('Pending Commenter', false);
        $response->assertDontSee('Rejected Commenter', false);

        $response->assertSeeInOrder(['First Commenter', 'Second Commenter'], false);
    }

    public function test_an_article_with_no_approved_comments_renders_no_comment_section(): void
    {
        $article = Article::live()->first();
        Comment::factory()->for($article)->create();

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertDontSee('id="comments-heading"', false);
    }

    public function test_comment_bodies_are_escaped_not_rendered_as_html(): void
    {
        $article = Article::live()->first();
        Comment::factory()->for($article)->approved()->create([
            'name' => 'Someone',
            'body' => 'Hello <script>alert(1)</script> there',
        ]);

        $content = $this->get(route('articles.show', $article))->getContent();

        // Visitor-submitted text is never rendered as HTML at any
        // privilege level.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    public function test_an_article_declares_og_type_article(): void
    {
        $article = Article::live()->first();

        $this->get(route('articles.show', $article))
            ->assertSee('<meta property="og:type" content="article">', false);

        // …while an ordinary page stays "website".
        $this->get(route('pages.about'))
            ->assertSee('<meta property="og:type" content="website">', false);
    }

    // -----------------------------------------------------------------
    // Packages
    // -----------------------------------------------------------------

    public function test_the_packages_page_lists_active_packages_and_hides_inactive_ones(): void
    {
        Package::factory()->inactive()->create(['name' => ['ar' => 'باقة معطّلة', 'en' => 'Inactive package']]);

        $response = $this->get(route('packages.index'));

        $response->assertOk();
        $response->assertDontSee('باقة معطّلة', false);

        foreach (Package::active()->ordered()->get() as $package) {
            $response->assertSee($package->getTranslation('name', 'ar'), false);
        }
    }

    public function test_a_package_with_no_price_reads_as_on_request_rather_than_free(): void
    {
        // Every seeded package ships price = null on purpose.
        $this->assertSame(Package::count(), Package::whereNull('price')->count());

        $this->get(route('packages.index'))
            ->assertSee(__('site.packages.price_on_request'), false)
            ->assertDontSee('>0<', false);
    }

    public function test_a_priced_package_shows_its_figure(): void
    {
        Package::factory()->create(['price' => 31500, 'sort_order' => 99]);

        $this->get(route('packages.index'))->assertSee('31,500', false);
    }

    public function test_the_comparison_table_marks_inclusion_when_packages_share_features(): void
    {
        Package::query()->delete();

        // CUMULATIVE lists — both name the same feature, so the matrix
        // says something.
        Package::factory()->create([
            'name' => ['ar' => 'باقة أ', 'en' => 'Package A'],
            'features' => [['ar' => 'شعار', 'en' => 'Logo'], ['ar' => 'دليل', 'en' => 'Guide']],
            'sort_order' => 0,
        ]);
        Package::factory()->create([
            'name' => ['ar' => 'باقة ب', 'en' => 'Package B'],
            'features' => [['ar' => 'شعار', 'en' => 'Logo']],
            'sort_order' => 1,
        ]);

        $response = $this->get(route('packages.index'));

        $response->assertOk();
        $response->assertSee(__('site.packages.compare'), false);
        $response->assertSee('شعار', false);
        $response->assertSee('دليل', false);
        // The yes/no is carried in an accessible name, not just a glyph.
        $response->assertSee(__('site.packages.included'), false);
        $response->assertSee(__('site.packages.not_included'), false);
    }

    /**
     * The seeded packages describe themselves INCREMENTALLY — "Everything
     * in Starter", then only what the tier adds. Rendered as a matrix
     * that produces exactly one tick per row and reads as "Enterprise
     * does not include a logo", which is the opposite of true.
     */
    public function test_the_comparison_table_is_skipped_when_packages_share_no_features(): void
    {
        Package::query()->delete();

        Package::factory()->create([
            'name' => ['ar' => 'باقة أ'],
            'features' => [['ar' => 'شعار']],
            'sort_order' => 0,
        ]);
        Package::factory()->create([
            'name' => ['ar' => 'باقة ب'],
            'features' => [['ar' => 'كل ما في باقة أ']],
            'sort_order' => 1,
        ]);

        $this->get(route('packages.index'))
            ->assertOk()
            ->assertDontSee(__('site.packages.compare'), false);
    }

    public function test_the_seeded_packages_render_their_cards_without_a_misleading_matrix(): void
    {
        $response = $this->get(route('packages.index'));

        $response->assertOk();
        // The cards still carry the full comparison.
        foreach (Package::active()->ordered()->get() as $package) {
            $response->assertSee($package->getTranslation('name', 'ar'), false);
        }
        $response->assertDontSee(__('site.packages.compare'), false);
    }

    public function test_a_single_package_never_renders_a_comparison_table(): void
    {
        Package::query()->delete();
        Package::factory()->create(['features' => [['ar' => 'شعار']]]);

        $this->get(route('packages.index'))->assertDontSee(__('site.packages.compare'), false);
    }

    // -----------------------------------------------------------------
    // Contact
    // -----------------------------------------------------------------

    public function test_the_contact_page_shows_nothing_invented_when_no_details_are_configured(): void
    {
        // The contact settings ship EMPTY rather than with placeholders.
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee(__('site.contact.no_details'), false);
    }

    public function test_configured_contact_details_appear(): void
    {
        Setting::updateOrCreate(['key' => 'contact_email'], ['value' => 'studio@creativemark.test', 'group' => 'contact']);
        app(SettingsRepository::class)->flush();

        $this->get(route('contact'))
            ->assertSee('studio@creativemark.test', false)
            ->assertDontSee(__('site.contact.no_details'), false);
    }
}
