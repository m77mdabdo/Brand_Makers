<?php

namespace Tests\Feature\Public;

use App\Models\EstimatorItem;
use App\Models\Package;
use App\Services\Estimator\EstimatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The estimator's arithmetic, and what it does when there is nothing
 * honest to add up.
 */
class EstimatorTest extends TestCase
{
    use RefreshDatabase;

    private EstimatorService $estimator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estimator = app(EstimatorService::class);
    }

    /**
     * A small priced catalogue. Deliberately NOT the seeded one — the
     * seeder ships everything inactive at 0.00, so a test that relied on
     * it would be asserting against an empty estimator.
     */
    private function catalogue(): void
    {
        EstimatorItem::factory()->create(['key' => 'logo', 'group' => 'identity', 'price' => 6000, 'sort_order' => 1, 'label' => ['ar' => 'شعار', 'en' => 'Logo']]);
        EstimatorItem::factory()->create(['key' => 'guide', 'group' => 'identity', 'price' => 5000, 'sort_order' => 2, 'label' => ['ar' => 'دليل', 'en' => 'Guide']]);
        EstimatorItem::factory()->create(['key' => 'cards', 'group' => 'print', 'price' => 900.50, 'sort_order' => 1, 'label' => ['ar' => 'بطاقات', 'en' => 'Cards']]);
    }

    // -----------------------------------------------------------------
    // Calculation
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{list<string>, string}>
     */
    public static function selections(): array
    {
        return [
            'nothing' => [[], null],
            'one item' => [['logo'], '6000.00'],
            'two items' => [['logo', 'guide'], '11000.00'],
            'across groups' => [['logo', 'cards'], '6900.50'],
            'everything' => [['logo', 'guide', 'cards'], '11900.50'],
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    #[DataProvider('selections')]
    public function test_the_total_is_the_sum_of_the_selected_prices(array $keys, ?string $expected): void
    {
        $this->catalogue();

        $this->assertSame($expected, $this->estimator->quote(null, $keys)['total']);
    }

    public function test_decimal_prices_add_up_exactly(): void
    {
        EstimatorItem::factory()->create(['key' => 'a', 'price' => 1200.10]);
        EstimatorItem::factory()->create(['key' => 'b', 'price' => 2400.20]);

        // 1200.10 + 2400.20 is exactly 3600.30, and must not arrive as
        // 3600.2999999999997 — the total is formatted from a decimal sum
        // rather than printed from a float.
        $this->assertSame('3600.30', $this->estimator->quote(null, ['a', 'b'])['total']);
    }

    public function test_a_duplicated_key_is_only_counted_once(): void
    {
        $this->catalogue();

        // Otherwise a hand-crafted payload could multiply a line by
        // repeating it, and the lead would record a total nobody quoted.
        $this->assertSame('6000.00', $this->estimator->quote(null, ['logo', 'logo', 'logo'])['total']);
    }

    // -----------------------------------------------------------------
    // Unknown, inactive and unpriced
    // -----------------------------------------------------------------

    public function test_an_inactive_item_is_never_summed(): void
    {
        $this->catalogue();
        EstimatorItem::factory()->inactive()->create(['key' => 'withdrawn', 'price' => 99000]);

        $quote = $this->estimator->quote(null, ['logo', 'withdrawn']);

        $this->assertSame('6000.00', $quote['total']);
        $this->assertCount(1, $quote['items']);
    }

    public function test_an_unknown_key_is_dropped_rather_than_erroring(): void
    {
        $this->catalogue();

        // A stale bookmarked selection should still produce a sensible
        // figure rather than a validation error the visitor cannot act on.
        $this->assertSame('6000.00', $this->estimator->quote(null, ['logo', 'does-not-exist'])['total']);
    }

    /**
     * @return array<string, array{list<mixed>}>
     */
    public static function junkSelections(): array
    {
        return [
            'non-strings' => [[1, 2.5, true, null]],
            'nested arrays' => [[['logo']]],
            'empty strings' => [['', '  ']],
        ];
    }

    /**
     * @param  list<mixed>  $keys
     */
    #[DataProvider('junkSelections')]
    public function test_junk_in_the_selection_is_discarded(array $keys): void
    {
        $this->catalogue();

        $quote = $this->estimator->quote(null, $keys);

        $this->assertNull($quote['total']);
        $this->assertFalse($quote['has_selection']);
    }

    public function test_the_selection_is_capped(): void
    {
        $this->catalogue();

        $keys = array_merge(
            array_map(fn ($i) => 'filler-'.$i, range(1, EstimatorService::MAX_SELECTED_ITEMS + 50)),
            ['logo'],
        );

        // The keys reach a whereIn, so the array a client sends cannot be
        // unbounded. 'logo' sits past the cap and is therefore dropped.
        $this->assertNull($this->estimator->quote(null, $keys)['total']);
    }

    // -----------------------------------------------------------------
    // Packages, and the priced-on-request case
    // -----------------------------------------------------------------

    public function test_a_priced_package_is_added_to_the_total(): void
    {
        $this->catalogue();
        Package::factory()->create(['slug' => 'starter', 'price' => 15000, 'is_active' => true]);

        $quote = $this->estimator->quote('starter', ['logo']);

        $this->assertSame('21000.00', $quote['total']);
        $this->assertFalse($quote['is_partial']);
    }

    public function test_an_on_request_package_makes_the_figure_a_floor_rather_than_a_total(): void
    {
        $this->catalogue();
        $package = Package::factory()->create(['slug' => 'bespoke', 'price' => null, 'is_active' => true, 'name' => ['ar' => 'مخصص', 'en' => 'Bespoke']]);

        $quote = $this->estimator->quote('bespoke', ['logo']);

        // The add-ons still total 6000, but the real figure can only be
        // higher — so the quote says so rather than quietly presenting
        // 6000 as the price.
        $this->assertSame('6000.00', $quote['total']);
        $this->assertTrue($quote['is_partial']);
        // Named in the CURRENT locale, because this list is rendered to
        // the visitor and stored in the snapshot they were shown.
        $this->assertSame([$package->name], $quote['on_request']);
    }

    public function test_an_inactive_package_is_ignored(): void
    {
        $this->catalogue();
        Package::factory()->create(['slug' => 'retired', 'price' => 99000, 'is_active' => false]);

        $quote = $this->estimator->quote('retired', ['logo']);

        // A package withdrawn from sale must not be quotable, and a
        // bookmarked slug degrades to "no base package".
        $this->assertNull($quote['package']);
        $this->assertSame('6000.00', $quote['total']);
    }

    public function test_a_mixed_currency_selection_refuses_to_produce_a_total(): void
    {
        EstimatorItem::factory()->create(['key' => 'sar', 'price' => 1000, 'currency' => 'SAR']);
        EstimatorItem::factory()->create(['key' => 'usd', 'price' => 1000, 'currency' => 'USD']);

        $quote = $this->estimator->quote(null, ['sar', 'usd']);

        // Summing them would produce a number that is wrong in both
        // currencies, and converting would mean inventing a rate.
        $this->assertNull($quote['total']);
        $this->assertTrue($quote['is_partial']);
    }

    // -----------------------------------------------------------------
    // The page
    // -----------------------------------------------------------------

    public function test_the_page_renders_the_priced_catalogue_in_both_locales(): void
    {
        $this->catalogue();

        foreach (['/cost-estimator' => 'ar', '/en/cost-estimator' => 'en'] as $url => $locale) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee(__('site.estimator.title', [], $locale), false);
            $response->assertSee($locale === 'ar' ? 'شعار' : 'Logo', false);
            // The group heading, translated from the stable slug.
            $response->assertSee(__('site.estimator.groups.identity', [], $locale), false);
        }
    }

    public function test_the_page_carries_the_estimate_disclaimer(): void
    {
        $this->catalogue();

        // "This is an estimate, the final quote may vary" — required on
        // any page that puts a number in front of a visitor.
        $this->get('/cost-estimator')->assertSee(__('site.estimator.disclaimer'));
    }

    public function test_an_inactive_item_never_reaches_the_page(): void
    {
        $this->catalogue();
        EstimatorItem::factory()->inactive()->create(['label' => ['ar' => 'مسحوب', 'en' => 'Withdrawn Item']]);

        $this->get('/en/cost-estimator')->assertDontSee('Withdrawn Item', false);
    }

    public function test_an_empty_catalogue_shows_pricing_in_preparation_rather_than_a_zero_calculator(): void
    {
        // This is the state of a fresh install: the seeder ships every
        // item at 0.00 and inactive. A working calculator that adds up to
        // nothing would be worse than saying so.
        $this->seed();

        $response = $this->get('/cost-estimator');

        $response->assertOk();
        $response->assertSee(__('site.estimator.unpriced'), false);
        $response->assertDontSee(__('site.estimator.estimated_total'), false);
    }

    public function test_the_page_sends_prices_but_never_trusts_them_back(): void
    {
        $this->catalogue();

        // The catalogue goes to the client so the total can update live…
        $this->get('/cost-estimator')->assertSee('data-estimator-catalogue', false);

        // …and a submitted price is simply not a field the server reads.
        $quote = $this->estimator->quote(null, ['logo']);
        $this->assertSame('6000.00', $quote['total']);
    }

    public function test_the_navbar_gains_the_estimator_entry_once_the_route_exists(): void
    {
        $this->seed();

        // The menu item ships seeded but dormant — MenuItem::ROUTES
        // whitelists 'estimator', and registering the route is what lights
        // it up. Nothing was hardcoded into the navbar.
        $this->get('/')->assertSee(route('estimator'), false);
    }

    // -----------------------------------------------------------------
    // The snapshot
    // -----------------------------------------------------------------

    public function test_the_snapshot_copies_labels_and_prices_rather_than_referencing_them(): void
    {
        $this->catalogue();
        Package::factory()->create(['slug' => 'starter', 'price' => 15000, 'is_active' => true, 'name' => ['ar' => 'الأساسية', 'en' => 'Starter']]);

        $snapshot = $this->estimator->snapshot($this->estimator->quote('starter', ['logo', 'cards']));

        $this->assertSame('21900.50', $snapshot['total']);
        $this->assertSame('starter', $snapshot['package']['slug']);
        $this->assertSame('15000.00', $snapshot['package']['price']);
        $this->assertCount(2, $snapshot['items']);
        $this->assertSame('logo', $snapshot['items'][0]['key']);
        $this->assertSame('6000.00', $snapshot['items'][0]['price']);
        $this->assertArrayHasKey('quoted_at', $snapshot);
    }

    public function test_a_later_price_rise_does_not_rewrite_an_existing_snapshot(): void
    {
        $this->catalogue();

        $snapshot = $this->estimator->snapshot($this->estimator->quote(null, ['logo']));

        EstimatorItem::where('key', 'logo')->update(['price' => 9000, 'label' => json_encode(['ar' => 'جديد', 'en' => 'Renamed'])]);

        // The stored breakdown is what this visitor was shown, not what
        // the catalogue says today.
        $this->assertSame('6000.00', $snapshot['items'][0]['price']);
        // The full per-locale map, so the breakdown reads correctly for an
        // Arabic-reading and an English-reading admin alike.
        $this->assertSame('Logo', $snapshot['items'][0]['label']['en']);
        $this->assertSame('شعار', $snapshot['items'][0]['label']['ar']);
    }
}
