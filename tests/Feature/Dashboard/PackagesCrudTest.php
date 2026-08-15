<?php

namespace Tests\Feature\Dashboard;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The pricing catalogue — the one screen that turns a seeded "on request"
 * package into a real price on the public site.
 */
class PackagesCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => ['ar' => 'باقة الإطلاق', 'en' => 'Launch package'],
            'description' => ['ar' => 'وصف', 'en' => 'Description'],
            'price' => '15000',
            'currency' => 'SAR',
            'features' => [
                'ar' => "تصميم الشعار\nلوحة الألوان\nدليل الهوية",
                'en' => "Logo design\nColour palette\nBrand guide",
            ],
            'is_active' => '1',
            'sort_order' => '1',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Gating
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{string, bool}>
     */
    public static function guardedScreens(): array
    {
        return [
            'index' => ['dashboard.packages.index', false],
            'create' => ['dashboard.packages.create', false],
            'edit' => ['dashboard.packages.edit', true],
        ];
    }

    #[DataProvider('guardedScreens')]
    public function test_the_screen_redirects_a_guest(string $route, bool $needsPackage): void
    {
        $url = $needsPackage ? route($route, Package::factory()->create()) : route($route);

        $this->get($url)->assertRedirect(route('login'));
    }

    #[DataProvider('guardedScreens')]
    public function test_the_screen_is_forbidden_to_a_non_admin(string $route, bool $needsPackage): void
    {
        $url = $needsPackage ? route($route, Package::factory()->create()) : route($route);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    #[DataProvider('guardedScreens')]
    public function test_an_admin_reaches_the_screen(string $route, bool $needsPackage): void
    {
        $url = $needsPackage ? route($route, Package::factory()->create()) : route($route);

        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    public function test_a_non_admin_cannot_write(): void
    {
        $package = Package::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('dashboard.packages.store'), $this->payload())
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->delete(route('dashboard.packages.destroy', $package))
            ->assertForbidden();

        $this->assertSame(1, Package::count());
    }

    public function test_the_sidebar_links_to_the_section(): void
    {
        // The entry sat in the sidebar definition for four build steps,
        // silently filtered out by Route::has() because the route did not
        // exist. This is the assertion that would have caught it.
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertSee(route('dashboard.packages.index'), false);
    }

    // -----------------------------------------------------------------
    // Create
    // -----------------------------------------------------------------

    public function test_an_admin_can_create_a_priced_package(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.packages.store'), $this->payload())
            ->assertRedirect(route('dashboard.packages.index'));

        $package = Package::sole();

        $this->assertSame('باقة الإطلاق', $package->getTranslation('name', 'ar'));
        $this->assertSame('Launch package', $package->getTranslation('name', 'en'));
        $this->assertSame('15000.00', $package->price);
        $this->assertSame('SAR', $package->currency);
        $this->assertTrue($package->is_active);
        $this->assertNotEmpty($package->slug);
    }

    public function test_features_are_stored_as_a_paired_per_locale_list(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.packages.store'), $this->payload());

        // A LIST of per-locale maps, not a translatable column — the
        // list's length is part of the content, and this shape is what
        // keeps the two locales paired.
        $this->assertSame([
            ['ar' => 'تصميم الشعار', 'en' => 'Logo design'],
            ['ar' => 'لوحة الألوان', 'en' => 'Colour palette'],
            ['ar' => 'دليل الهوية', 'en' => 'Brand guide'],
        ], Package::sole()->features);
    }

    public function test_an_english_feature_without_an_arabic_counterpart_is_dropped(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.packages.store'), $this->payload([
            'features' => ['ar' => 'بند واحد', 'en' => "One item\nOrphaned second item"],
        ]));

        // Arabic drives the count. An orphaned English line would render
        // as a bullet that is blank on the default locale.
        $this->assertSame([['ar' => 'بند واحد', 'en' => 'One item']], Package::sole()->features);
    }

    public function test_an_arabic_feature_without_english_is_kept(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.packages.store'), $this->payload([
            'features' => ['ar' => "بند أول\nبند ثانٍ", 'en' => 'First item'],
        ]));

        $this->assertSame([
            ['ar' => 'بند أول', 'en' => 'First item'],
            ['ar' => 'بند ثانٍ'],
        ], Package::sole()->features);
    }

    public function test_blank_feature_lines_are_dropped(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.packages.store'), $this->payload([
            'features' => ['ar' => "أول\n\n  \nثانٍ", 'en' => ''],
        ]));

        $this->assertSame([['ar' => 'أول'], ['ar' => 'ثانٍ']], Package::sole()->features);
    }

    public function test_a_blank_price_means_on_request_not_free(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.packages.store'), $this->payload(['price' => '']));

        $package = Package::sole();

        // null and 0.00 are different claims, and the public card renders
        // them differently.
        $this->assertNull($package->price);
        $this->assertTrue($package->isPriceOnRequest());
    }

    public function test_a_zero_price_is_stored_as_zero_not_null(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.packages.store'), $this->payload(['price' => '0']));

        $package = Package::sole();

        $this->assertSame('0.00', $package->price);
        $this->assertFalse($package->isPriceOnRequest());
    }

    public function test_markup_in_a_name_or_description_is_stripped(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.packages.store'), $this->payload([
            'name' => ['ar' => 'باقة <script>alert(1)</script>'],
            'description' => ['ar' => 'وصف <b>عريض</b>'],
        ]));

        $package = Package::sole();

        // A heading and a one-line caption are not places for markup.
        $this->assertStringNotContainsString('<script>', $package->getTranslation('name', 'ar'));
        $this->assertStringNotContainsString('<b>', $package->getTranslation('description', 'ar'));
    }

    // -----------------------------------------------------------------
    // The popular flag
    // -----------------------------------------------------------------

    public function test_marking_a_package_popular_clears_the_others(): void
    {
        $existing = Package::factory()->create(['is_popular' => true]);

        $this->actingAs($this->admin)->post(route('dashboard.packages.store'), $this->payload(['is_popular' => '1']));

        // "Most popular" means one. Two badges is not a state anybody
        // chooses on purpose.
        $this->assertFalse($existing->fresh()->is_popular);
        $this->assertSame(1, Package::where('is_popular', true)->count());
    }

    public function test_editing_a_package_to_popular_also_clears_the_others(): void
    {
        $first = Package::factory()->create(['is_popular' => true]);
        $second = Package::factory()->create(['is_popular' => false]);

        $this->actingAs($this->admin)->put(route('dashboard.packages.update', $second), $this->payload(['is_popular' => '1']));

        $this->assertFalse($first->fresh()->is_popular);
        $this->assertTrue($second->fresh()->is_popular);
    }

    public function test_unticking_popular_leaves_no_package_highlighted(): void
    {
        $package = Package::factory()->create(['is_popular' => true]);

        $payload = $this->payload();
        unset($payload['is_popular']);

        $this->actingAs($this->admin)->put(route('dashboard.packages.update', $package), $payload);

        // An unticked checkbox is absent from the payload, and absence
        // must mean false rather than "leave alone".
        $this->assertFalse($package->fresh()->is_popular);
        $this->assertSame(0, Package::where('is_popular', true)->count());
    }

    // -----------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'no Arabic name' => [['name' => ['ar' => '', 'en' => 'English only']], 'name.ar'],
            'a negative price' => [['price' => '-1'], 'price'],
            'a non-numeric price' => [['price' => 'free'], 'price'],
            'a price past the column' => [['price' => '99999999999999'], 'price'],
            'a four-letter currency' => [['currency' => 'SARS'], 'currency'],
            'a slug with spaces' => [['slug' => 'not a slug'], 'slug'],
            'a negative sort order' => [['sort_order' => '-1'], 'sort_order'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_an_invalid_payload_is_rejected(array $overrides, string $field): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.packages.store'), $this->payload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Package::count());
    }

    public function test_a_duplicate_slug_is_reported_rather_than_500ing(): void
    {
        Package::factory()->create(['slug' => 'launch']);

        // Rule::unique counts soft-deleted rows, matching the DB index —
        // otherwise validation passes and the insert dies.
        $this->actingAs($this->admin)
            ->post(route('dashboard.packages.store'), $this->payload(['slug' => 'launch']))
            ->assertSessionHasErrors('slug');
    }

    public function test_a_soft_deleted_slug_still_blocks_a_new_one(): void
    {
        $package = Package::factory()->create(['slug' => 'retired']);
        $package->delete();

        $this->actingAs($this->admin)
            ->post(route('dashboard.packages.store'), $this->payload(['slug' => 'retired']))
            ->assertSessionHasErrors('slug');
    }

    public function test_saving_a_package_without_changing_its_slug_is_allowed(): void
    {
        $package = Package::factory()->create(['slug' => 'growth']);

        // ->ignore() the row being edited, or the slug reports itself as
        // taken.
        $this->actingAs($this->admin)
            ->put(route('dashboard.packages.update', $package), $this->payload(['slug' => 'growth']))
            ->assertSessionHasNoErrors();
    }

    public function test_a_key_outside_the_definition_is_never_written(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.packages.store'), $this->payload([
            'id' => 999,
            'created_at' => '1999-01-01 00:00:00',
        ]));

        $package = Package::sole();

        $this->assertNotSame(999, $package->id);
        $this->assertTrue($package->created_at->isToday());
    }

    // -----------------------------------------------------------------
    // Update and delete
    // -----------------------------------------------------------------

    public function test_an_admin_can_reprice_a_package(): void
    {
        $package = Package::factory()->create(['price' => null]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.packages.update', $package), $this->payload(['price' => '22500.50']))
            ->assertRedirect(route('dashboard.packages.index'));

        $this->assertSame('22500.50', $package->fresh()->price);
    }

    public function test_deleting_a_package_soft_deletes_it(): void
    {
        $package = Package::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('dashboard.packages.destroy', $package))
            ->assertRedirect(route('dashboard.packages.index'));

        // Orders and package enquiries resolve withTrashed(), so retiring
        // a package must keep every historical record naming it.
        $this->assertSoftDeleted($package);
    }

    // -----------------------------------------------------------------
    // The public site reflects it
    // -----------------------------------------------------------------

    public function test_a_new_package_appears_on_the_public_pricing_page(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.packages.store'), $this->payload());

        $this->get('/en/packages')
            ->assertSee('Launch package', false)
            ->assertSee('15,000', false)
            ->assertSee('Logo design', false);
    }

    public function test_pricing_a_package_replaces_on_request_with_the_figure(): void
    {
        $package = Package::factory()->create(['price' => null, 'name' => ['ar' => 'باقة', 'en' => 'Package']]);

        $this->get('/en/packages')->assertSee(__('site.packages.price_on_request', [], 'en'), false);

        $this->actingAs($this->admin)->put(route('dashboard.packages.update', $package), $this->payload(['price' => '9000']));

        $this->get('/en/packages')->assertSee('9,000', false);
    }

    public function test_deactivating_a_package_removes_it_from_the_public_page(): void
    {
        $package = Package::factory()->create(['is_active' => true, 'name' => ['ar' => 'باقة', 'en' => 'Visible Package']]);

        $this->get('/en/packages')->assertSee('Visible Package', false);

        $payload = $this->payload(['name' => ['ar' => 'باقة', 'en' => 'Visible Package']]);
        unset($payload['is_active']);

        $this->actingAs($this->admin)->put(route('dashboard.packages.update', $package), $payload);

        $this->get('/en/packages')->assertDontSee('Visible Package', false);
    }

    public function test_the_popular_badge_shows_on_the_public_page(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.packages.store'), $this->payload(['is_popular' => '1']));

        $this->get('/en/packages')->assertSee(__('site.packages.popular', [], 'en'), false);
    }

    public function test_a_priced_package_becomes_selectable_in_the_estimator(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.packages.store'), $this->payload(['price' => '15000']));

        // The estimator's base-package selector reads the same catalogue,
        // and an unpriced package contributes nothing to an estimate.
        $this->get('/en/cost-estimator')
            ->assertSee('Launch package', false)
            ->assertSee('15,000', false);
    }

    // -----------------------------------------------------------------
    // The index warns about the seeded state
    // -----------------------------------------------------------------

    public function test_the_index_warns_while_packages_are_unpriced(): void
    {
        Package::factory()->count(2)->create(['price' => null]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.packages.index'))
            ->assertSee(trans_choice('dashboard.packages.unpriced_notice', 2, ['count' => 2]));
    }

    public function test_the_warning_disappears_once_everything_is_priced(): void
    {
        Package::factory()->create(['price' => 5000]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.packages.index'))
            ->assertDontSee(trans_choice('dashboard.packages.unpriced_notice', 1, ['count' => 1]));
    }
}
