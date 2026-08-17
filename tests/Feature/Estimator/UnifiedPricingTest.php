<?php

namespace Tests\Feature\Estimator;

use App\Models\EstimatorGroup;
use App\Models\EstimatorItem;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use App\Services\Estimator\EstimatorService;
use App\Support\Honeypot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * One price per thing, set in the dashboard, live on the site.
 *
 * A service costs what /dashboard/services says it costs — the services
 * page and the estimator both read that column, so the two surfaces
 * cannot disagree and neither needs a code change to follow it. The
 * acquisition options are countries priced one by one, because acquiring
 * in the UAE and acquiring in Egypt are different pieces of work.
 *
 * The rule underneath all of it: NOTHING in a submitted payload
 * contributes a number. The client sends identifiers; the server re-reads
 * every figure. These tests assert that from the outside.
 */
class UnifiedPricingTest extends TestCase
{
    use RefreshDatabase;

    private EstimatorService $estimator;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->estimator = app(EstimatorService::class);
        $this->admin = User::where('is_admin', true)->firstOrFail();
    }

    /**
     * The dashboard form posts every field, including the checkboxes —
     * a partial payload would read as "unticked" and deactivate the row,
     * which is a property of the boolean handling rather than of pricing.
     *
     * @return array<string, mixed>
     */
    private function servicePayload(Service $service, array $overrides = []): array
    {
        return array_merge([
            'slug' => $service->slug,
            'icon' => $service->icon,
            'is_active' => '1',
            'is_flagship' => $service->is_flagship ? '1' : '0',
            'sort_order' => $service->sort_order,
            'name' => $service->getTranslations('name'),
            'summary' => $service->getTranslations('summary'),
            'body' => $service->getTranslations('body'),
            'requirements' => $service->getTranslations('requirements'),
            'process' => $service->getTranslations('process'),
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // A service price reaches BOTH surfaces
    // -----------------------------------------------------------------

    public function test_a_price_set_in_the_dashboard_appears_on_the_services_page_and_computes_in_the_estimator(): void
    {
        $service = Service::where('slug', 'llc-formation')->firstOrFail();

        $this->assertTrue($service->isPriceOnRequest(), 'Services ship unpriced.');

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.update', $service), $this->servicePayload($service, ['price' => '4500']))
            ->assertSessionHasNoErrors();

        // Surface one: the services page.
        $this->get('/services')->assertOk()->assertSee('4,500', false);

        // Surface two: the estimator, same column, no second edit.
        $this->assertSame('4500.00', $this->estimator->quote(null, [], ['llc-formation'])['total']);
    }

    #[DataProvider('locales')]
    public function test_an_unpriced_service_reads_as_on_request_on_the_services_page(string $locale, string $prefix): void
    {
        // Every seeded service ships with no figure, so this is the state
        // of a fresh install rather than a state built for the test.
        $this->assertSame(Service::count(), Service::whereNull('price')->count());

        $this->get($prefix.'/services')
            ->assertOk()
            ->assertSee(__('site.services.on_request', [], $locale), false)
            // A bare 0 would say the service is free.
            ->assertDontSee('>0 ', false);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function locales(): array
    {
        return ['Arabic' => ['ar', ''], 'English' => ['en', '/en']];
    }

    public function test_an_unpriced_service_is_selectable_and_makes_the_quote_a_floor(): void
    {
        $priced = Service::where('slug', 'llc-formation')->firstOrFail();
        $priced->price = 4500;
        $priced->save();

        $quote = $this->estimator->quote(null, [], ['llc-formation', 'consulting-and-studies']);

        // The on-request service adds nothing but is NAMED, so the visitor
        // can see which part of their selection is missing from the figure.
        $this->assertSame('4500.00', $quote['total']);
        $this->assertTrue($quote['is_partial']);
        $this->assertContains(
            Service::where('slug', 'consulting-and-studies')->value('name'),
            $quote['on_request']
        );
    }

    public function test_the_services_selector_is_the_service_catalogue_not_a_copy_of_it(): void
    {
        $group = EstimatorGroup::where('key', 'services')->firstOrFail();

        $this->assertTrue($group->isServiceSourced());

        // No estimator item may shadow a service: a visitor could then
        // select both and be charged for the same work twice.
        $this->assertSame(
            [],
            EstimatorItem::whereIn('key', Service::pluck('slug')->all())->pluck('key')->all()
        );

        $options = $this->estimator->catalogue()['groups']->firstWhere('key', 'services')['items'];

        $this->assertSame(Service::active()->ordered()->pluck('slug')->all(), $options->pluck('slug')->all());
    }

    public function test_renaming_a_service_renames_the_estimator_option_with_no_second_edit(): void
    {
        $service = Service::where('slug', 'llc-formation')->firstOrFail();

        $this->actingAs($this->admin)->put(
            route('dashboard.services.update', $service),
            $this->servicePayload($service, ['name' => ['ar' => 'اسم جديد تماماً', 'en' => 'A brand new name'], 'price' => '1000'])
        )->assertSessionHasNoErrors();

        $this->get('/cost-estimator')->assertOk()->assertSee('اسم جديد تماماً', false);
    }

    // -----------------------------------------------------------------
    // Acquisition, by country
    // -----------------------------------------------------------------

    public function test_the_acquisition_group_is_single_select_and_priced_per_country(): void
    {
        $group = EstimatorGroup::where('key', 'acquisition_country')->firstOrFail();

        $this->assertFalse($group->allowsMultiple(), 'A visitor acquires in ONE country.');
        $this->assertGreaterThan(1, $group->items()->count());

        // Seeded unpriced, like every other add-on.
        $this->assertSame(
            $group->items()->count(),
            $group->items()->whereNull('price')->count()
        );
    }

    public function test_a_country_priced_in_the_dashboard_appears_and_computes_on_the_public_estimator(): void
    {
        $country = EstimatorItem::where('key', 'acquisition-uae')->firstOrFail();

        $this->actingAs($this->admin)->put(route('dashboard.estimator-items.update', $country), [
            'label' => $country->getTranslations('label'),
            'group' => 'acquisition_country',
            'price' => '8000',
            'is_active' => '1',
            'sort_order' => 0,
        ])->assertSessionHasNoErrors();

        $this->get('/cost-estimator')->assertOk()->assertSee('8,000', false);

        $this->assertSame('8000.00', $this->estimator->quote(null, ['acquisition-uae'])['total']);
    }

    public function test_a_country_the_admin_adds_shows_up_without_a_code_change(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.estimator-items.store'), [
            'key' => 'acquisition-turkiye',
            'label' => ['ar' => 'تركيا', 'en' => 'Türkiye'],
            'group' => 'acquisition_country',
            'price' => '9500',
            'is_active' => '1',
            'sort_order' => 20,
        ])->assertSessionHasNoErrors();

        $this->get('/cost-estimator')->assertOk()->assertSee('تركيا', false)->assertSee('9,500', false);
        $this->assertSame('9500.00', $this->estimator->quote(null, ['acquisition-turkiye'])['total']);
    }

    public function test_a_deactivated_country_stops_being_quotable(): void
    {
        $country = EstimatorItem::where('key', 'acquisition-uae')->firstOrFail();
        $country->update(['price' => 8000, 'is_active' => false]);

        // A bookmarked selection must not resurrect a withdrawn option.
        $this->assertNull($this->estimator->quote(null, ['acquisition-uae'])['total']);
    }

    // -----------------------------------------------------------------
    // The whole sum
    // -----------------------------------------------------------------

    public function test_a_fully_priced_selection_computes_an_exact_total(): void
    {
        Service::where('slug', 'llc-formation')->update(['price' => 4500]);
        EstimatorItem::where('key', 'acquisition-uae')->update(['price' => 8000, 'is_active' => true]);
        EstimatorItem::where('key', 'attestations')->update(['price' => 1200, 'is_active' => true]);

        $quote = $this->estimator->quote('formation-basic', ['acquisition-uae', 'attestations'], ['llc-formation']);

        // 13,000 base + 4,500 service + 8,000 country + 1,200 add-on.
        $this->assertSame('26700.00', $quote['total']);
        $this->assertFalse($quote['is_partial'], 'Nothing in this selection is on request.');
    }

    public function test_an_unpriced_line_anywhere_makes_the_figure_a_floor_without_breaking_it(): void
    {
        Service::where('slug', 'llc-formation')->update(['price' => 4500]);

        // acquisition-uae stays unpriced.
        $quote = $this->estimator->quote('formation-basic', ['acquisition-uae'], ['llc-formation']);

        $this->assertSame('17500.00', $quote['total']);
        $this->assertTrue($quote['is_partial']);
        $this->assertNotEmpty($quote['on_request']);
    }

    // -----------------------------------------------------------------
    // Prices are not settable from a public path
    // -----------------------------------------------------------------

    public function test_a_public_submission_cannot_set_a_price_anywhere(): void
    {
        Service::where('slug', 'llc-formation')->update(['price' => 4500]);
        EstimatorItem::where('key', 'acquisition-uae')->update(['price' => 8000, 'is_active' => true]);

        $this->post(route('estimator.store'), [
            'full_name' => 'مستثمر',
            'email' => 'investor@example.test',
            'consent_given' => '1',
            Honeypot::FIELD => '',
            'package' => 'formation-basic',
            'services' => ['llc-formation'],
            'items' => ['acquisition-uae'],

            // Every one of these is a figure a crafted payload would love
            // to control. None of them is validated, read, or written.
            'price' => '1',
            'estimate_total' => '1',
            'services_price' => '1',
            'total' => '1',
        ])->assertRedirect();

        $lead = Lead::where('type', Lead::TYPE_ESTIMATE)->sole();

        // Recomputed from the catalogue: 13,000 + 4,500 + 8,000.
        $this->assertSame('25500.00', $lead->estimate_total);

        // And the catalogue itself is untouched by the request.
        $this->assertSame('4500.00', Service::where('slug', 'llc-formation')->value('price'));
        $this->assertSame('8000.00', EstimatorItem::where('key', 'acquisition-uae')->value('price'));
    }

    public function test_price_is_not_mass_assignable_on_a_service(): void
    {
        $service = new Service;
        $service->fill(['price' => 999, 'slug' => 'x']);

        // Guarded on the model, so even a controller that handed request
        // data straight to fill() could not set it.
        $this->assertNull($service->price);
    }

    // -----------------------------------------------------------------
    // The lead snapshot
    // -----------------------------------------------------------------

    public function test_the_lead_snapshot_captures_the_package_services_country_and_add_ons(): void
    {
        Service::where('slug', 'llc-formation')->update(['price' => 4500]);
        EstimatorItem::where('key', 'acquisition-uae')->update(['price' => 8000, 'is_active' => true]);
        EstimatorItem::where('key', 'attestations')->update(['price' => 1200, 'is_active' => true]);

        $this->post(route('estimator.store'), [
            'full_name' => 'مستثمر',
            'email' => 'investor@example.test',
            'consent_given' => '1',
            Honeypot::FIELD => '',
            'package' => 'formation-basic',
            'services' => ['llc-formation'],
            'items' => ['acquisition-uae', 'attestations'],
        ])->assertRedirect();

        $snapshot = Lead::where('type', Lead::TYPE_ESTIMATE)->sole()->estimate_snapshot;

        $this->assertSame('formation-basic', $snapshot['package']['slug']);
        $this->assertSame('13000.00', $snapshot['package']['price']);

        // Services are recorded separately from items: they come from a
        // different table, and a reader of an old lead should not have to
        // guess which lines were which.
        $this->assertCount(1, $snapshot['services']);
        $this->assertSame('llc-formation', $snapshot['services'][0]['slug']);
        $this->assertSame('4500.00', $snapshot['services'][0]['price']);
        // The full per-locale map, so the breakdown reads for either admin.
        $this->assertArrayHasKey('ar', $snapshot['services'][0]['name']);
        $this->assertArrayHasKey('en', $snapshot['services'][0]['name']);

        $country = collect($snapshot['items'])->firstWhere('key', 'acquisition-uae');
        $this->assertSame('acquisition_country', $country['group']);
        $this->assertSame('8000.00', $country['price']);

        $this->assertNotNull(collect($snapshot['items'])->firstWhere('key', 'attestations'));
        $this->assertSame('26700.00', $snapshot['total']);
    }

    public function test_the_snapshot_records_an_on_request_line_as_null_rather_than_zero(): void
    {
        Package::where('slug', 'formation-basic')->update(['price' => 13000]);

        $this->post(route('estimator.store'), [
            'full_name' => 'مستثمر',
            'email' => 'investor@example.test',
            'consent_given' => '1',
            Honeypot::FIELD => '',
            'package' => 'formation-basic',
            'services' => ['llc-formation'],
        ])->assertRedirect();

        $snapshot = Lead::where('type', Lead::TYPE_ESTIMATE)->sole()->estimate_snapshot;

        // 0.00 would read as free when somebody opens this lead months
        // later; null keeps saying "we had not priced it".
        $this->assertNull($snapshot['services'][0]['price']);
        $this->assertTrue($snapshot['is_partial']);
    }
}
