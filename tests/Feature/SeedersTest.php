<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\BookingAvailability;
use App\Models\Country;
use App\Models\EstimatorItem;
use App\Models\Faq;
use App\Models\LeadSource;
use App\Models\MenuItem;
use App\Models\Package;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TrackingSetting;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\LeadSourceSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_production_seed_creates_the_expected_baseline(): void
    {
        $this->seed();

        $this->assertSame(1, User::admins()->count());
        $this->assertSame(count(LeadSourceSeeder::sources()), LeadSource::count());
        // Derived from the definition rather than hard-coded: the seeder
        // and the editor read the same list, so the count following it is
        // the actual invariant.
        $this->assertSame(count(SettingsSeeder::definitions()), Setting::count());
        $this->assertSame(count(TrackingSetting::FORMATS), TrackingSetting::count());
        $this->assertSame(0, TrackingSetting::where('is_active', true)->count(), 'No pixel fires on a fresh install.');
        $this->assertSame(7, BookingAvailability::count(), 'Every weekday gets a row, including the closed ones.');
    }

    public function test_seeding_twice_creates_no_duplicates(): void
    {
        $this->seed();

        $before = $this->contentCounts();

        $this->seed();

        $this->assertSame($before, $this->contentCounts());
    }

    public function test_the_admin_user_has_a_hashed_password_and_the_admin_flag(): void
    {
        $this->seed();

        $admin = User::admins()->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->is_admin);
        $this->assertStringStartsWith('$2y$', $admin->password);
    }

    /**
     * The placeholder credentials are published in the seeder source, so
     * seeding a real environment without ADMIN_EMAIL / ADMIN_PASSWORD must
     * abort rather than silently create an account whose password is
     * public knowledge.
     */
    public function test_the_admin_seeder_refuses_placeholder_credentials_outside_local(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_EMAIL and ADMIN_PASSWORD must be set');

        try {
            (new AdminUserSeeder)->run();
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_the_admin_seeder_still_uses_the_dev_fallback_in_local(): void
    {
        app()->detectEnvironment(fn () => 'local');

        try {
            (new AdminUserSeeder)->run();
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }

        $this->assertSame(1, User::admins()->count());
    }

    public function test_seeded_lead_sources_and_menu_items_are_marked_system(): void
    {
        $this->seed();

        $this->assertSame(LeadSource::count(), LeadSource::where('is_system', true)->count());
        $this->assertSame(MenuItem::count(), MenuItem::system()->count());
    }

    /**
     * Menu items are seeded against the route they will EVENTUALLY point
     * at, but the seeder only writes a route link when that route actually
     * exists — anything not built yet becomes a link to the homepage
     * section where its content currently lives.
     *
     * So the invariant is not "every item has a whitelisted route". It is
     * that every item's destination is REAL: a route link must be both
     * whitelisted and registered, and a url link must resolve.
     */
    public function test_every_seeded_menu_item_points_somewhere_real(): void
    {
        $this->seed();

        foreach (MenuItem::all() as $item) {
            if ($item->link_type === 'route') {
                $this->assertArrayHasKey(
                    $item->route_name,
                    MenuItem::ROUTE_WHITELIST,
                    "Seeded menu item {$item->key} points at a non-whitelisted route."
                );

                $this->assertTrue(
                    Route::has($item->route_name),
                    "Seeded menu item {$item->key} is a route link to an unregistered route."
                );

                continue;
            }

            if ($item->link_type === 'url') {
                $this->assertNotNull(
                    $item->href(),
                    "Seeded menu item {$item->key} is a url link that resolves to nothing."
                );

                continue;
            }

            // 'none' is the dormant state: the dedicated route does not
            // exist yet and there is no homepage section to stand in for
            // it, so the navbar renders nothing for this row.
            $this->assertSame('none', $item->link_type);
            $this->assertNull($item->href());
        }
    }

    public function test_seeded_menu_items_all_name_a_whitelisted_eventual_route(): void
    {
        $this->seed();

        // Whatever a row currently links to, the route it is heading
        // towards has to be one the navbar is allowed to reach — the
        // whitelist is what stops a menu entry resolving to a route that
        // needs a bound model and 500-ing every page.
        foreach (MenuItem::whereNotNull('route_name')->get() as $item) {
            $this->assertArrayHasKey($item->route_name, MenuItem::ROUTE_WHITELIST);
        }
    }

    public function test_seeded_packages_carry_no_invented_price(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, Package::count());
        $this->assertSame(
            Package::count(),
            Package::whereNull('price')->count(),
            'A seeded price would put a figure the studio never agreed on the public pricing page.'
        );
    }

    public function test_seeded_estimator_items_ship_inactive_until_someone_prices_them(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, EstimatorItem::count());
        $this->assertSame(0, EstimatorItem::active()->count());
        $this->assertSame('0.00', EstimatorItem::estimateFor(EstimatorItem::pluck('key')->all()));
    }

    public function test_re_seeding_never_overwrites_a_price_an_admin_has_entered(): void
    {
        $this->seed();

        $package = Package::first();
        $package->update(['price' => 12500]);

        $item = EstimatorItem::first();
        $item->update(['price' => 4000, 'is_active' => true]);

        $this->seed();

        $this->assertSame('12500.00', (string) $package->fresh()->price);
        $this->assertSame('4000.00', (string) $item->fresh()->price);
        $this->assertTrue($item->fresh()->is_active);
    }

    /**
     * PageContentSeeder is create-only by design: `db:seed` must never
     * destroy prose an admin has refined in the dashboard.
     */
    public function test_re_seeding_never_overwrites_page_content(): void
    {
        $this->seed();

        $page = Page::where('slug', 'privacy-policy')->firstOrFail();
        $page->setTranslation('body', 'ar', '<p>نص كتبه المشرف بنفسه.</p>');
        $page->save();

        $this->seed();

        $this->assertSame('<p>نص كتبه المشرف بنفسه.</p>', $page->fresh()->getTranslation('body', 'ar'));
    }

    public function test_re_seeding_never_reopens_booking_hours_an_admin_has_closed(): void
    {
        $this->seed();

        $sunday = BookingAvailability::where('day_of_week', BookingAvailability::SUNDAY)->firstOrFail();
        $sunday->update(['is_open' => false, 'opens_at' => '11:00:00']);

        $this->seed();

        $this->assertFalse($sunday->fresh()->is_open);
        $this->assertStringStartsWith('11:00', $sunday->fresh()->opens_at);
    }

    /**
     * @return array<string, int>
     */
    private function contentCounts(): array
    {
        return [
            'users' => User::count(),
            'lead_sources' => LeadSource::count(),
            'settings' => Setting::count(),
            'tracking_settings' => TrackingSetting::count(),
            'booking_availability' => BookingAvailability::count(),
            'services' => Service::count(),
            'packages' => Package::count(),
            'estimator_items' => EstimatorItem::count(),
            'countries' => Country::count(),
            'faqs' => Faq::count(),
            'articles' => Article::count(),
            'pages' => Page::count(),
            'page_sections' => PageSection::count(),
            'seo_meta' => SeoMeta::count(),
            'menu_items' => MenuItem::count(),
        ];
    }
}
