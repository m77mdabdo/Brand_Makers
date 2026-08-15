<?php

namespace Tests\Feature\Public;

use App\Models\MenuItem;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The public navbar is rendered from menu_items, so every rule about what
 * it may contain has to hold at render time — not merely in the editor.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * The desktop nav markup only, so assertions cannot be satisfied by
     * an unrelated link elsewhere on the page.
     */
    private function desktopNav(string $url = '/'): string
    {
        preg_match('/<nav class="hidden.*?<\/nav>/s', $this->get($url)->getContent(), $matches);

        $this->assertNotEmpty($matches, 'The page rendered no desktop navigation.');

        return $matches[0];
    }

    public function test_the_navbar_renders_the_seeded_menu_in_order(): void
    {
        $labels = MenuItem::topLevel()->visible()->ordered()->get()
            ->filter(fn (MenuItem $item) => $item->href() !== null || $item->visibleChildren->isNotEmpty())
            ->map(fn (MenuItem $item) => $item->getTranslation('label', 'ar'))
            ->values()
            ->all();

        $this->assertNotEmpty($labels);
        $this->get('/')->assertSeeInOrder($labels, false);
    }

    public function test_it_renders_only_routes_that_are_actually_registered(): void
    {
        $nav = $this->desktopNav();

        preg_match_all('/href="([^"#]+)"/', $nav, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach (array_unique($matches[1]) as $href) {
            $path = parse_url($href, PHP_URL_PATH) ?: '/';

            $this->assertNotNull(
                Route::getRoutes()->match(Request::create($path, 'GET')),
                "The navbar links to an unroutable path: {$path}"
            );
        }
    }

    public function test_an_item_whose_route_does_not_exist_yet_is_dropped_not_rendered_dead(): void
    {
        /*
         * A sentinel name, not a real-but-unbuilt one: this fixture named
         * packages.index and then orders.track, and both were subsequently
         * built. What is under test is that an unresolvable route is
         * dropped rather than rendered dead, which holds for any name the
         * router does not know.
         */
        $unregistered = 'never.to.be.registered';

        $item = MenuItem::factory()->create([
            'label' => ['ar' => 'قسم غير جاهز', 'en' => 'Unbuilt section'],
            'link_type' => 'route',
            'route_name' => $unregistered,
        ]);

        $this->assertFalse(Route::has($unregistered));
        $this->assertNull($item->href());

        $this->get('/')->assertOk()->assertDontSee('قسم غير جاهز', false);
    }

    public function test_a_hidden_item_never_reaches_the_public_navbar(): void
    {
        $item = MenuItem::factory()->hidden()->create([
            'label' => ['ar' => 'عنصر مخفي', 'en' => 'Hidden item'],
            'link_type' => 'route',
            'route_name' => 'home',
        ]);

        $this->get('/')->assertDontSee('عنصر مخفي', false);
    }

    public function test_the_navbar_survives_an_empty_menu_and_still_offers_a_way_home(): void
    {
        MenuItem::query()->delete();

        foreach (['/', '/en'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee(__('site.nav.home'), false);
        }
    }

    // -----------------------------------------------------------------
    // Locale-aware hrefs
    // -----------------------------------------------------------------

    public function test_hrefs_stay_inside_the_current_locale(): void
    {
        $arabic = $this->desktopNav('/');
        $english = $this->desktopNav('/en');

        preg_match_all('/href="([^"]+)"/', $arabic, $ar);
        preg_match_all('/href="([^"]+)"/', $english, $en);

        foreach ($ar[1] as $href) {
            $path = parse_url($href, PHP_URL_PATH) ?: '/';
            $this->assertStringStartsNotWith('/en', $path, "Arabic navbar links into /en: {$href}");
        }

        // Every English nav link must stay under /en — a bare route()
        // instead of lroute() would send an English visitor to the Arabic
        // site from every link in the bar.
        foreach ($en[1] as $href) {
            $path = parse_url($href, PHP_URL_PATH) ?: '/';
            $this->assertTrue(
                $path === '/en' || str_starts_with($path, '/en/') || str_starts_with($path, '#'),
                "English navbar link left /en: {$href}"
            );
        }
    }

    // -----------------------------------------------------------------
    // Active state
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     */
    public static function activePages(): array
    {
        return [
            'home' => ['home', 'nav-home'],
            'services index' => ['services.index', 'nav-services'],
            'packages' => ['packages.index', 'nav-packages'],
            'articles index' => ['articles.index', 'nav-articles'],
            'about' => ['pages.about', 'nav-about'],
            'contact' => ['contact', 'nav-contact'],
        ];
    }

    #[DataProvider('activePages')]
    public function test_exactly_one_item_is_marked_current_on_its_own_page(string $route, string $key): void
    {
        foreach ([route($route), route($route.'.en')] as $url) {
            $nav = $this->desktopNav($url);

            $this->assertSame(
                1,
                substr_count($nav, 'aria-current="page"'),
                "Expected exactly one current item on {$url}."
            );
        }
    }

    public function test_an_index_item_stays_lit_on_its_show_pages(): void
    {
        $service = Service::active()->ordered()->first();

        $nav = $this->desktopNav(route('services.show', $service));

        // ".index owns its .show" is the one generalisation the matcher
        // makes — a visitor reading a single service should still see
        // "Services" highlighted.
        $this->assertSame(1, substr_count($nav, 'aria-current="page"'));
        $this->assertTrue(
            MenuItem::where('key', 'nav-services')->first()->isActive(),
            'The services index item should be active on a service page.'
        );
    }

    public function test_a_prefix_match_never_lights_up_unrelated_pages(): void
    {
        // Four items share the "pages." prefix. A loose first-segment
        // match would light all of them at once on any one of them.
        $this->assertGreaterThanOrEqual(
            2,
            collect(MenuItem::ROUTE_WHITELIST)->keys()->filter(fn ($n) => str_starts_with($n, 'pages.'))->count()
        );

        $nav = $this->desktopNav(route('pages.about'));

        $this->assertSame(1, substr_count($nav, 'aria-current="page"'));
    }

    public function test_an_index_item_does_not_match_a_deeper_route_that_merely_shares_its_prefix(): void
    {
        $item = MenuItem::factory()->route('articles.index')->make();

        /*
         * Driven through REAL requests rather than a mocked facade.
         *
         * The rule reads the current route via the router, and stubbing
         * that with repeated Mockery expectations silently returns the
         * first stub for every later call — which made an earlier version
         * of this test pass while asserting nothing.
         */
        Route::middleware('web')->get('/_nav-show', fn () => '')->name('articles.show');
        Route::middleware('web')->get('/_nav-deeper', fn () => '')->name('articles.comments.store');
        Route::middleware('web')->get('/_nav-deeper-index', fn () => '')->name('articles.index.something');
        Route::getRoutes()->refreshNameLookups();

        // ".index" owns its ".show" …
        $this->get('/_nav-show');
        $this->assertTrue($item->isActive());

        // … and nothing else that merely shares the prefix.
        $this->get('/_nav-deeper');
        $this->assertFalse($item->isActive(), 'articles.index must not match articles.comments.store.');

        $this->get('/_nav-deeper-index');
        $this->assertFalse($item->isActive(), 'articles.index must not match articles.index.something.');
    }

    // -----------------------------------------------------------------
    // Dropdowns and link safety
    // -----------------------------------------------------------------

    public function test_a_parent_renders_its_visible_children_and_hides_the_rest(): void
    {
        $parent = MenuItem::factory()->dropdownParent()->create([
            'label' => ['ar' => 'قائمة', 'en' => 'Group'],
        ]);
        MenuItem::factory()->route('faqs.index')->create([
            'parent_id' => $parent->id,
            'label' => ['ar' => 'ابن ظاهر', 'en' => 'Visible child'],
        ]);
        MenuItem::factory()->route('faqs.index')->hidden()->create([
            'parent_id' => $parent->id,
            'label' => ['ar' => 'ابن مخفي', 'en' => 'Hidden child'],
        ]);

        $nav = $this->desktopNav();

        $this->assertStringContainsString('قائمة', $nav);
        $this->assertStringContainsString('ابن ظاهر', $nav);
        $this->assertStringNotContainsString('ابن مخفي', $nav);
    }

    public function test_a_parent_lights_up_when_one_of_its_children_is_current(): void
    {
        $parent = MenuItem::factory()->dropdownParent()->create(['label' => ['ar' => 'قائمة']]);
        MenuItem::factory()->route('faqs.index')->create([
            'parent_id' => $parent->id,
            'label' => ['ar' => 'الأسئلة'],
        ]);

        $item = MenuItem::where('id', $parent->id)->with('visibleChildren')->first();

        $this->get(route('faqs.index'));

        $this->assertTrue($item->isActive(), 'A dropdown parent should light up for its active child.');
    }

    public function test_external_links_carry_rel_noopener(): void
    {
        MenuItem::factory()->url('https://example.test', '_blank')->create([
            'label' => ['ar' => 'رابط خارجي', 'en' => 'External'],
        ]);

        $nav = $this->desktopNav();

        // Without rel="noopener" the opened page can reach back through
        // window.opener and navigate this one.
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="https:\/\/example\.test"[^>]*rel="noopener"/s',
            $nav
        );
    }

    public function test_a_same_window_link_emits_no_rel_attribute(): void
    {
        $item = MenuItem::factory()->url('/somewhere')->create();

        $this->assertNull($item->linkRel());
    }
}
