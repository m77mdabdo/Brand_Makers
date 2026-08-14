<?php

namespace Tests\Feature\Public;

use App\Models\Campaign;
use App\Models\EstimatorItem;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Package;
use App\Models\User;
use App\Support\Honeypot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Send me this estimate" — the optional step that turns a selection into
 * a lead.
 */
class EstimateLeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        EstimatorItem::factory()->create(['key' => 'logo', 'group' => 'identity', 'price' => 6000, 'sort_order' => 1, 'label' => ['ar' => 'شعار', 'en' => 'Logo']]);
        EstimatorItem::factory()->create(['key' => 'guide', 'group' => 'identity', 'price' => 5000, 'sort_order' => 2, 'label' => ['ar' => 'دليل', 'en' => 'Guide']]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'full_name' => 'نورة',
            'email' => 'noura@example.test',
            'items' => ['logo', 'guide'],
            'consent_given' => '1',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // The happy path
    // -----------------------------------------------------------------

    public function test_a_submission_creates_an_estimate_lead_with_the_computed_total(): void
    {
        $this->post(route('estimator.store'), $this->payload())
            ->assertRedirect(lroute('estimator').'#estimate-form')
            ->assertSessionHas('status', __('site.flash.estimate_submitted'));

        $lead = Lead::sole();

        $this->assertSame(Lead::TYPE_ESTIMATE, $lead->type);
        $this->assertSame('11000.00', $lead->estimate_total);
        $this->assertTrue($lead->consent_given);
        $this->assertNotNull($lead->consented_at);
    }

    public function test_the_snapshot_records_the_itemised_breakdown(): void
    {
        $this->post(route('estimator.store'), $this->payload());

        $snapshot = Lead::sole()->estimate_snapshot;

        $this->assertSame('11000.00', $snapshot['total']);
        $this->assertSame('SAR', $snapshot['currency']);
        $this->assertFalse($snapshot['is_partial']);
        $this->assertSame(['logo', 'guide'], array_column($snapshot['items'], 'key'));
        $this->assertSame(['6000.00', '5000.00'], array_column($snapshot['items'], 'price'));
    }

    public function test_a_base_package_is_recorded_both_as_a_relation_and_in_the_snapshot(): void
    {
        $package = Package::factory()->create(['slug' => 'starter', 'price' => 15000, 'is_active' => true]);

        $this->post(route('estimator.store'), $this->payload(['package' => 'starter']));

        $lead = Lead::sole();

        // package_id so an estimate is filterable by package like any
        // other lead; the snapshot so the figure stays readable after the
        // package is renamed or repriced.
        $this->assertSame($package->id, $lead->package_id);
        $this->assertSame('26000.00', $lead->estimate_total);
        $this->assertSame('starter', $lead->estimate_snapshot['package']['slug']);
    }

    public function test_an_estimate_with_no_selection_at_all_still_records_the_enquiry(): void
    {
        $this->post(route('estimator.store'), $this->payload(['items' => []]))->assertRedirect();

        $lead = Lead::sole();

        // Someone who just wants to be contacted is still a lead. null,
        // not 0.00 — there is no total, which is a different claim.
        $this->assertSame(Lead::TYPE_ESTIMATE, $lead->type);
        $this->assertNull($lead->estimate_total);
    }

    public function test_an_all_on_request_estimate_stores_a_null_total_rather_than_zero(): void
    {
        Package::factory()->create(['slug' => 'bespoke', 'price' => null, 'is_active' => true]);

        $this->post(route('estimator.store'), $this->payload(['package' => 'bespoke', 'items' => []]));

        $lead = Lead::sole();

        $this->assertNull($lead->estimate_total);
        $this->assertTrue($lead->estimate_snapshot['is_partial']);
    }

    // -----------------------------------------------------------------
    // The client is never trusted
    // -----------------------------------------------------------------

    public function test_a_submitted_price_or_total_is_ignored_entirely(): void
    {
        $this->post(route('estimator.store'), $this->payload([
            'total' => '1.00',
            'estimate_total' => '1.00',
            'price' => '1.00',
            'items' => ['logo'],
        ]));

        // The total is recomputed from the catalogue. A payload that could
        // set its own figure would be the number the business then quoted
        // against.
        $this->assertSame('6000.00', Lead::sole()->estimate_total);
    }

    public function test_an_inactive_item_cannot_be_smuggled_into_an_estimate(): void
    {
        EstimatorItem::factory()->inactive()->create(['key' => 'withdrawn', 'price' => 99000]);

        $this->post(route('estimator.store'), $this->payload(['items' => ['logo', 'withdrawn']]));

        $lead = Lead::sole();

        $this->assertSame('6000.00', $lead->estimate_total);
        $this->assertSame(['logo'], array_column($lead->estimate_snapshot['items'], 'key'));
    }

    public function test_an_unknown_item_key_does_not_break_the_submission(): void
    {
        $this->post(route('estimator.store'), $this->payload(['items' => ['logo', 'nonsense']]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('6000.00', Lead::sole()->estimate_total);
    }

    // -----------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------

    public function test_the_name_email_and_consent_are_required(): void
    {
        $this->post(route('estimator.store'), ['items' => ['logo']])
            ->assertSessionHasErrors(['full_name', 'email', 'consent_given']);

        $this->assertSame(0, Lead::count());
    }

    // -----------------------------------------------------------------
    // Honeypot, throttle, attribution, locale
    // -----------------------------------------------------------------

    public function test_the_honeypot_produces_an_identical_response_and_writes_nothing(): void
    {
        $real = $this->post(route('estimator.store'), $this->payload());
        Lead::query()->delete();

        $trapped = $this->post(route('estimator.store'), $this->payload([
            Honeypot::FIELD => 'https://spam.example',
        ]));

        // Byte-identical outcome: a different status, or a different
        // flash, would tell a bot author which field to leave alone.
        $this->assertSame($real->getStatusCode(), $trapped->getStatusCode());
        $this->assertSame($real->headers->get('Location'), $trapped->headers->get('Location'));
        $trapped->assertSessionHas('status', __('site.flash.estimate_submitted'));

        $this->assertSame(0, Lead::count());
    }

    public function test_submissions_are_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('estimator.store'), $this->payload(['email' => "v{$i}@example.test"]))->assertRedirect();
        }

        $this->post(route('estimator.store'), $this->payload(['email' => 'sixth@example.test']))
            ->assertStatus(429);

        $this->assertSame(5, Lead::count());
    }

    public function test_attribution_is_resolved_and_stored(): void
    {
        $campaign = Campaign::factory()->create(['external_campaign_id' => 'camp-estimate']);
        // The canonical channel row. Without it AttributionService
        // correctly falls back to the raw value, so the assertion below
        // would be testing the fallback rather than the resolution.
        LeadSource::factory()->create(['key' => 'instagram']);

        $this->post(route('estimator.store'), $this->payload([
            'latest_touch_snapshot' => json_encode([
                'utm_source' => 'INSTAGRAM',
                'utm_campaign' => 'spring',
                'campaign_id' => 'camp-estimate',
                'fbclid' => 'fb-1',
            ]),
            'first_touch_snapshot' => json_encode(['utm_source' => 'google']),
        ]));

        $lead = Lead::sole();

        $this->assertSame('instagram', $lead->source_platform);
        $this->assertSame($campaign->id, $lead->linked_campaign_id);
        // The raw external id is untouched — a different identifier space
        // from the resolved foreign key.
        $this->assertSame('camp-estimate', $lead->campaign_id);
        $this->assertSame('google', $lead->first_touch['utm_source']);
    }

    public function test_an_english_submission_redirects_back_to_the_english_page(): void
    {
        $this->post(route('estimator.store.en'), $this->payload())
            ->assertRedirect(route('estimator.en').'#estimate-form');
    }

    // -----------------------------------------------------------------
    // The dashboard
    // -----------------------------------------------------------------

    public function test_the_breakdown_is_visible_on_the_leads_show_screen(): void
    {
        $this->post(route('estimator.store'), $this->payload());

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('dashboard.leads.show', Lead::sole()));

        $response->assertOk();
        $response->assertSee(__('dashboard.leads.estimate'), false);
        // Every line, and the total.
        $response->assertSee('شعار', false);
        $response->assertSee('6,000.00', false);
        $response->assertSee('11,000.00', false);
    }

    public function test_the_breakdown_shows_what_was_quoted_not_the_current_price_list(): void
    {
        $this->post(route('estimator.store'), $this->payload(['items' => ['logo']]));

        EstimatorItem::where('key', 'logo')->update(['price' => 9999]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard.leads.show', Lead::sole()))
            ->assertSee('6,000.00', false)
            ->assertDontSee('9,999.00', false);
    }

    public function test_an_estimate_lead_appears_under_its_own_type_filter(): void
    {
        $this->post(route('estimator.store'), $this->payload());
        Lead::factory()->create(['type' => Lead::TYPE_CONTACT, 'full_name' => 'Contact Person']);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard.leads.index', ['type' => Lead::TYPE_ESTIMATE]))
            ->assertSee('نورة', false)
            ->assertDontSee('Contact Person', false);
    }
}
