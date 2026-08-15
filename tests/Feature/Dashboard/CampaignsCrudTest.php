<?php

namespace Tests\Feature\Dashboard;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use App\Services\Marketing\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The campaign register — and the back-linking that makes attribution's
 * campaign half work in production for the first time.
 */
class CampaignsCrudTest extends TestCase
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
            'name' => 'Spring brand push',
            'platform' => 'meta',
            'external_campaign_id' => 'camp-spring-2026',
            'budget' => '50000',
            'spend' => '12500',
            'currency' => 'SAR',
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-04-30',
            'is_active' => '1',
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
            'index' => ['dashboard.campaigns.index', false],
            'create' => ['dashboard.campaigns.create', false],
            'edit' => ['dashboard.campaigns.edit', true],
        ];
    }

    #[DataProvider('guardedScreens')]
    public function test_the_screen_redirects_a_guest(string $route, bool $needsCampaign): void
    {
        $url = $needsCampaign ? route($route, Campaign::factory()->create()) : route($route);

        $this->get($url)->assertRedirect(route('login'));
    }

    #[DataProvider('guardedScreens')]
    public function test_the_screen_is_forbidden_to_a_non_admin(string $route, bool $needsCampaign): void
    {
        $url = $needsCampaign ? route($route, Campaign::factory()->create()) : route($route);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    #[DataProvider('guardedScreens')]
    public function test_an_admin_reaches_the_screen(string $route, bool $needsCampaign): void
    {
        $url = $needsCampaign ? route($route, Campaign::factory()->create()) : route($route);

        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    public function test_a_non_admin_cannot_write(): void
    {
        $campaign = Campaign::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('dashboard.campaigns.store'), $this->payload())
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->delete(route('dashboard.campaigns.destroy', $campaign))
            ->assertForbidden();

        $this->assertSame(1, Campaign::count());
    }

    public function test_the_sidebar_links_to_the_section(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertSee(route('dashboard.campaigns.index'), false);
    }

    // -----------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------

    public function test_an_admin_can_create_a_campaign(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.campaigns.store'), $this->payload())
            ->assertRedirect(route('dashboard.campaigns.index'));

        $campaign = Campaign::sole();

        $this->assertSame('Spring brand push', $campaign->name);
        $this->assertSame('meta', $campaign->platform);
        $this->assertSame('camp-spring-2026', $campaign->external_campaign_id);
        $this->assertSame('50000.00', $campaign->budget);
        $this->assertSame('12500.00', $campaign->spend);
        $this->assertSame('2026-03-01', $campaign->starts_on->toDateString());
        $this->assertTrue($campaign->is_active);
    }

    public function test_an_admin_can_edit_a_campaign(): void
    {
        $campaign = Campaign::factory()->create(['spend' => 0]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.campaigns.update', $campaign), $this->payload(['spend' => '31000']))
            ->assertRedirect(route('dashboard.campaigns.index'));

        $this->assertSame('31000.00', $campaign->fresh()->spend);
    }

    public function test_a_blank_budget_is_null_rather_than_zero(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.campaigns.store'), $this->payload([
            'budget' => '', 'spend' => '',
        ]));

        $campaign = Campaign::sole();

        // "No budget recorded" and "a budget of nothing" are different —
        // the second would read as 100% overspent.
        $this->assertNull($campaign->budget);
        $this->assertNull($campaign->spend);
    }

    public function test_unticking_active_deactivates_the_campaign(): void
    {
        $campaign = Campaign::factory()->create(['is_active' => true]);

        $payload = $this->payload();
        unset($payload['is_active']);

        $this->actingAs($this->admin)->put(route('dashboard.campaigns.update', $campaign), $payload);

        $this->assertFalse($campaign->fresh()->is_active);
    }

    public function test_deleting_a_campaign_keeps_its_leads_and_their_raw_id(): void
    {
        $campaign = Campaign::factory()->create(['external_campaign_id' => 'camp-x']);
        $lead = Lead::factory()->create([
            'campaign_id' => 'camp-x',
            'linked_campaign_id' => $campaign->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.campaigns.destroy', $campaign))
            ->assertRedirect(route('dashboard.campaigns.index'));

        $lead->refresh();

        // The enquiry survives and keeps the raw string it arrived with;
        // only the resolved join is removed.
        $this->assertTrue($lead->exists);
        $this->assertNull($lead->linked_campaign_id);
        $this->assertSame('camp-x', $lead->campaign_id);
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
            'no name' => [['name' => ''], 'name'],
            'an unknown platform' => [['platform' => 'myspace'], 'platform'],
            'a negative budget' => [['budget' => '-1'], 'budget'],
            'a non-numeric spend' => [['spend' => 'lots'], 'spend'],
            'a four-letter currency' => [['currency' => 'SARS'], 'currency'],
            'an end date before the start' => [['starts_on' => '2026-05-01', 'ends_on' => '2026-04-01'], 'ends_on'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_an_invalid_payload_is_rejected(array $overrides, string $field): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.campaigns.store'), $this->payload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Campaign::count());
    }

    public function test_a_one_day_campaign_is_allowed(): void
    {
        // after_or_equal, not after.
        $this->actingAs($this->admin)
            ->post(route('dashboard.campaigns.store'), $this->payload([
                'starts_on' => '2026-03-01', 'ends_on' => '2026-03-01',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_duplicate_external_id_is_rejected(): void
    {
        Campaign::factory()->create(['external_campaign_id' => 'camp-spring-2026']);

        // Two campaigns sharing one id would make the attribution match
        // ambiguous — whichever row came back first would own the lead.
        $this->actingAs($this->admin)
            ->post(route('dashboard.campaigns.store'), $this->payload())
            ->assertSessionHasErrors('external_campaign_id');
    }

    public function test_saving_a_campaign_without_changing_its_external_id_is_allowed(): void
    {
        $campaign = Campaign::factory()->create(['external_campaign_id' => 'camp-spring-2026']);

        $this->actingAs($this->admin)
            ->put(route('dashboard.campaigns.update', $campaign), $this->payload())
            ->assertSessionHasNoErrors();
    }

    public function test_a_key_outside_the_definition_is_never_written(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.campaigns.store'), $this->payload([
            'id' => 999,
            'created_at' => '1999-01-01 00:00:00',
        ]));

        $campaign = Campaign::sole();

        $this->assertNotSame(999, $campaign->id);
        $this->assertTrue($campaign->created_at->isToday());
    }

    // -----------------------------------------------------------------
    // Back-linking — the reason this screen exists
    // -----------------------------------------------------------------

    public function test_creating_a_campaign_back_links_leads_that_arrived_first(): void
    {
        // The production reality before this screen existed: the ad ran,
        // enquiries arrived carrying its id, and nothing could create the
        // campaign row to match them against.
        $early = Lead::factory()->create(['campaign_id' => 'camp-spring-2026', 'linked_campaign_id' => null]);
        $viaUtm = Lead::factory()->create(['utm_campaign' => 'camp-spring-2026', 'linked_campaign_id' => null]);
        $unrelated = Lead::factory()->create(['campaign_id' => 'camp-other', 'linked_campaign_id' => null]);

        $this->actingAs($this->admin)->post(route('dashboard.campaigns.store'), $this->payload());

        $campaign = Campaign::sole();

        $this->assertSame($campaign->id, $early->fresh()->linked_campaign_id);
        $this->assertSame($campaign->id, $viaUtm->fresh()->linked_campaign_id);
        $this->assertNull($unrelated->fresh()->linked_campaign_id);
    }

    public function test_back_linking_matches_both_columns_like_attribution_does(): void
    {
        // AttributionService tries campaign_id then utm_campaign. If the
        // two ever disagreed, live attribution and reconciliation would
        // give different answers for the same lead.
        Lead::factory()->create(['campaign_id' => 'camp-x', 'utm_campaign' => null, 'linked_campaign_id' => null]);
        Lead::factory()->create(['campaign_id' => null, 'utm_campaign' => 'camp-x', 'linked_campaign_id' => null]);

        $campaign = Campaign::factory()->create(['external_campaign_id' => 'camp-x']);

        $this->assertSame(2, app(CampaignService::class)->relinkLeads($campaign));
    }

    public function test_back_linking_never_steals_a_lead_from_another_campaign(): void
    {
        $existing = Campaign::factory()->create(['external_campaign_id' => 'camp-a']);
        $lead = Lead::factory()->create([
            'campaign_id' => 'camp-shared',
            'linked_campaign_id' => $existing->id,
        ]);

        $newer = Campaign::factory()->create(['external_campaign_id' => 'camp-shared']);
        app(CampaignService::class)->relinkLeads($newer);

        // Only nulls are filled. Repointing somebody else's attribution
        // would corrupt the reporting this feature exists to feed.
        $this->assertSame($existing->id, $lead->fresh()->linked_campaign_id);
    }

    public function test_a_campaign_with_no_external_id_links_nothing(): void
    {
        Lead::factory()->count(3)->create(['campaign_id' => null, 'linked_campaign_id' => null]);

        $campaign = Campaign::factory()->create(['external_campaign_id' => null]);

        // Otherwise an empty string would match every lead with a null id.
        $this->assertSame(0, app(CampaignService::class)->relinkLeads($campaign));
        $this->assertSame(0, Lead::whereNotNull('linked_campaign_id')->count());
    }

    public function test_correcting_a_mistyped_external_id_picks_up_the_leads(): void
    {
        $campaign = Campaign::factory()->create(['external_campaign_id' => 'camp-typo']);
        $lead = Lead::factory()->create(['campaign_id' => 'camp-correct', 'linked_campaign_id' => null]);

        $this->assertNull($lead->fresh()->linked_campaign_id);

        // The commonest edit, and the one that must reconcile.
        $this->actingAs($this->admin)->put(route('dashboard.campaigns.update', $campaign), $this->payload([
            'external_campaign_id' => 'camp-correct',
        ]));

        $this->assertSame($campaign->id, $lead->fresh()->linked_campaign_id);
    }

    public function test_the_admin_is_told_how_many_leads_were_attributed(): void
    {
        Lead::factory()->count(2)->create(['campaign_id' => 'camp-spring-2026', 'linked_campaign_id' => null]);

        // Back-linking is invisible otherwise, and the count is how an
        // admin tells whether the id they typed matches what the platform
        // actually sends.
        $this->actingAs($this->admin)
            ->post(route('dashboard.campaigns.store'), $this->payload())
            ->assertSessionHas('status', trans_choice('dashboard.campaigns.saved_with_leads', 2, ['count' => 2]));
    }

    // -----------------------------------------------------------------
    // End to end: a live submission now resolves its campaign
    // -----------------------------------------------------------------

    public function test_a_public_submission_resolves_a_campaign_created_in_the_dashboard(): void
    {
        // The seeder already ships the 13 canonical lead sources,
        // instagram among them — creating another would collide on the
        // unique key.
        $this->seed();

        $this->actingAs($this->admin)->post(route('dashboard.campaigns.store'), $this->payload([
            'external_campaign_id' => 'camp-live',
        ]));

        $campaign = Campaign::where('external_campaign_id', 'camp-live')->sole();

        // The half that was already built and tested, now with something
        // to match against for the first time in production.
        $this->post(route('contact.store'), [
            'full_name' => 'نورة',
            'email' => 'noura@example.test',
            'message' => 'مرحبا',
            'consent_given' => '1',
            'latest_touch_snapshot' => json_encode([
                'utm_source' => 'INSTAGRAM',
                'campaign_id' => 'camp-live',
            ]),
        ])->assertRedirect();

        $lead = Lead::latest('id')->firstOrFail();

        $this->assertSame($campaign->id, $lead->linked_campaign_id);
        // The raw string is untouched — a different identifier space.
        $this->assertSame('camp-live', $lead->campaign_id);
    }

    public function test_the_index_shows_the_linked_lead_count(): void
    {
        $campaign = Campaign::factory()->create(['external_campaign_id' => 'camp-count', 'name' => 'Counted Campaign']);
        Lead::factory()->count(3)->create(['linked_campaign_id' => $campaign->id]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.campaigns.index'))
            ->assertSee('Counted Campaign', false)
            // The count renders inside a link with surrounding
            // whitespace, so match the element rather than a bare ">3<".
            ->assertSeeInOrder(['Counted Campaign', '3'], false);
    }
}
