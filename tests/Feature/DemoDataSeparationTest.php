<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Booking;
use App\Models\BookingBlackoutDate;
use App\Models\Campaign;
use App\Models\ConversionEvent;
use App\Models\Country;
use App\Models\EstimatorItem;
use App\Models\Faq;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Package;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\VisitorSession;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the production/demo split.
 *
 * Fabricated testimonials on the public site, invented prices in the
 * estimator, or made-up revenue driving the reporting figures would all be
 * actively misleading — so the default seed must produce none of them.
 */
class DemoDataSeparationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tables the production seed must leave completely empty, and the
     * reason each one would mislead someone.
     *
     * @return array<string, class-string>
     */
    private function fabricatedOnlyTables(): array
    {
        return [
            'Testimonials are invented client quotes.' => Testimonial::class,
            'Sample campaigns carry made-up budget and spend.' => Campaign::class,
            'Sample leads are enquiries nobody sent.' => Lead::class,
            'Sample revenue would corrupt every ROI figure.' => ConversionEvent::class,
            'Sample bookings are appointments nobody made.' => Booking::class,
            'Sample orders are projects nobody ordered.' => Order::class,
            'Order events belong to orders that do not exist.' => OrderEvent::class,
            'Visitor journeys belong to visitors who never came.' => VisitorSession::class,
            'Blackout dates are demo calendar entries.' => BookingBlackoutDate::class,
        ];
    }

    public function test_the_production_seed_creates_no_fabricated_rows(): void
    {
        $this->seed();

        foreach ($this->fabricatedOnlyTables() as $why => $model) {
            $this->assertSame(0, $model::count(), $why);
        }
    }

    public function test_the_production_seed_still_creates_real_content(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, Service::count());
        $this->assertGreaterThan(0, Package::count());
        $this->assertGreaterThan(0, EstimatorItem::count());
        $this->assertGreaterThan(0, Country::count());
        $this->assertGreaterThan(0, Faq::count());
        $this->assertGreaterThan(0, Page::count());
        $this->assertGreaterThan(0, Article::count());
        $this->assertGreaterThan(0, SeoMeta::count());
    }

    /**
     * The strongest form of the assertion: every demo row carries a
     * literal marker in text a human reads, and the production seed emits
     * that string nowhere in the database at all.
     */
    public function test_no_production_row_carries_the_sample_data_marker(): void
    {
        $this->seed();

        $marker = DemoDataSeeder::SAMPLE_MARKER;
        $found = [];

        foreach ($this->textColumns() as $table => $columns) {
            foreach ($columns as $column) {
                $hits = DB::table($table)->where($column, 'like', '%'.$marker.'%')->count();

                if ($hits > 0) {
                    $found[] = "{$table}.{$column} ({$hits} rows)";
                }
            }
        }

        $this->assertSame([], $found, 'Production content must never be labelled sample data: '.implode(', ', $found));
    }

    /**
     * The production seed once carried no price at all. It now carries
     * exactly three, because the business set them — which does not
     * weaken the rule, it narrows it: a figure may ship only if someone
     * agreed it.
     *
     * The estimator's ADD-ON catalogue is still entirely unpriced and
     * inactive, and that is what this guards. The three tiers reach the
     * estimator as its optional package base (EstimatorService::quote),
     * not as line items — so ticking every add-on still adds nothing.
     */
    public function test_the_production_seed_prices_only_what_the_business_agreed(): void
    {
        $this->seed();

        $this->assertSame(3, Package::whereNotNull('price')->count());

        // Add-ons ship VISIBLE now, labelled على الطلب, so "active" is no
        // longer the thing to assert — an active line may legitimately
        // carry no price. What must hold is that none of them carries a
        // figure nobody agreed.
        $this->assertSame(
            0,
            EstimatorItem::whereNotNull('price')->count(),
            'An add-on may not carry a price until someone sets it.'
        );

        $this->assertSame(
            '0.00',
            EstimatorItem::estimateFor(EstimatorItem::pluck('key')->all()),
            'A visitor who ticked every add-on must not be quoted an invented total.'
        );
    }

    /**
     * The three tiers must not ALSO exist as estimator line items — a
     * visitor could then pick the package and its twin item and be
     * charged for the tier twice.
     */
    public function test_the_package_tiers_are_not_duplicated_as_estimator_items(): void
    {
        $this->seed();

        $this->assertSame(
            [],
            EstimatorItem::whereIn('key', Package::pluck('slug')->all())->pluck('key')->all()
        );
    }

    public function test_the_production_seed_reports_no_revenue(): void
    {
        $this->seed();

        $this->assertSame(0, ConversionEvent::count());
        $this->assertNull(ConversionEvent::sum('value') ?: null);
        $this->assertSame(0, Lead::converted()->count());
        $this->assertSame(0, Campaign::whereNotNull('spend')->count());
    }

    public function test_the_demo_seeder_adds_the_sample_rows_when_run_explicitly(): void
    {
        $this->seed();
        $this->seed(DemoDataSeeder::class);

        foreach ($this->fabricatedOnlyTables() as $why => $model) {
            $this->assertGreaterThan(0, $model::count(), "Demo seeding should have populated {$model}.");
        }

        $this->assertGreaterThan(0, EstimatorItem::active()->count(), 'The demo seed makes the estimator usable.');
    }

    public function test_every_demo_row_is_labelled_as_sample_data(): void
    {
        $this->seed();
        $this->seed(DemoDataSeeder::class);

        $marker = DemoDataSeeder::SAMPLE_MARKER;

        $this->assertSame(Testimonial::count(), Testimonial::where('client_title', 'like', '%'.$marker.'%')->count());
        $this->assertSame(Lead::count(), Lead::where('full_name', 'like', '%'.$marker.'%')->count());
        $this->assertSame(Campaign::count(), Campaign::where('name', 'like', '%'.$marker.'%')->count());
        $this->assertSame(Booking::count(), Booking::where('name', 'like', '%'.$marker.'%')->count());
        $this->assertSame(Order::count(), Order::where('client_name', 'like', '%'.$marker.'%')->count());
    }

    public function test_the_demo_seeder_is_idempotent(): void
    {
        $this->seed();
        $this->seed(DemoDataSeeder::class);

        $before = array_map(fn ($model) => $model::count(), array_values($this->fabricatedOnlyTables()));

        $this->seed(DemoDataSeeder::class);

        $this->assertSame($before, array_map(fn ($model) => $model::count(), array_values($this->fabricatedOnlyTables())));
    }

    /**
     * Even fabricated data goes through the real, guarded write paths —
     * otherwise the code that has to be correct in production is never the
     * code exercised in development.
     */
    public function test_demo_orders_still_get_a_real_hashed_tracking_code(): void
    {
        $this->seed();
        $this->seed(DemoDataSeeder::class);

        foreach (Order::all() as $order) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $order->tracking_code_hash);
            $this->assertMatchesRegularExpression('/^CM-\d{4}-[2-9A-HJ-NP-Z]{6}$/', $order->reference);
            $this->assertNull($order->trackingCode(), 'The plaintext is not recoverable from a stored order.');
        }

        $this->assertSame(Order::count(), Order::distinct()->count('tracking_code_hash'));
    }

    public function test_demo_visitor_sessions_still_store_only_truncated_ips(): void
    {
        $this->seed();
        $this->seed(DemoDataSeeder::class);

        foreach (VisitorSession::whereNotNull('ip_prefix')->pluck('ip_prefix') as $prefix) {
            $this->assertStringEndsWith('.0', $prefix, "Demo data introduced a non-truncated address: {$prefix}");
        }
    }

    /**
     * Text columns worth scanning for the sample marker. Deliberately
     * hand-listed rather than derived: this is the set a human would
     * actually read in the dashboard or on the public site, which is where
     * a mislabelled row would do damage.
     *
     * @return array<string, list<string>>
     */
    private function textColumns(): array
    {
        return [
            'testimonials' => ['client_name', 'client_title', 'quote'],
            'leads' => ['full_name', 'message', 'email'],
            'campaigns' => ['name', 'notes'],
            'conversion_events' => ['notes'],
            'bookings' => ['name', 'notes', 'email'],
            'orders' => ['client_name', 'notes', 'client_email'],
            'order_events' => ['note'],
            'services' => ['name', 'summary', 'body'],
            'packages' => ['name', 'description'],
            'estimator_items' => ['label'],
            'countries' => ['name', 'notes'],
            'faqs' => ['question', 'answer'],
            'articles' => ['title', 'excerpt', 'body'],
            'pages' => ['title', 'body'],
            'page_sections' => ['content'],
            'settings' => ['value'],
            'menu_items' => ['label'],
            'seo_meta' => ['meta_title', 'meta_description'],
            'booking_blackout_dates' => ['reason'],
        ];
    }
}
