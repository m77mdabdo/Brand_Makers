<?php

namespace Tests\Feature\Public;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Services\Marketing\AttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Marketing attribution: what the client reports, and what the server
 * makes of it.
 */
class AttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * @param  array<string, mixed>  $latest
     * @param  array<string, mixed>|null  $first
     * @return array<string, mixed>
     */
    private function resolve(array $latest, ?array $first = null): array
    {
        return app(AttributionService::class)->resolve(Request::create('/contact', 'POST', [
            'latest_touch_snapshot' => json_encode($latest),
            'first_touch_snapshot' => json_encode($first ?? $latest),
        ]));
    }

    // -----------------------------------------------------------------
    // Snapshots
    // -----------------------------------------------------------------

    public function test_both_snapshots_are_stored_verbatim(): void
    {
        $first = ['utm_source' => 'google', 'utm_campaign' => 'spring', 'landing_page' => '/'];
        $latest = ['utm_source' => 'instagram', 'utm_campaign' => 'retarget', 'landing_page' => '/packages'];

        $resolved = $this->resolve($latest, $first);

        $this->assertSame($first, $resolved['first_touch']);
        $this->assertSame($latest, $resolved['latest_touch']);
    }

    public function test_the_flat_columns_come_from_the_latest_touch_not_the_first(): void
    {
        $resolved = $this->resolve(
            latest: ['utm_source' => 'instagram', 'utm_medium' => 'paid', 'gclid' => 'latest-click'],
            first: ['utm_source' => 'google', 'utm_medium' => 'organic', 'gclid' => 'first-click'],
        );

        // Latest-touch is what immediately preceded THIS conversion, so it
        // is the honest single answer to "where did this lead come from".
        $this->assertSame('instagram', $resolved['utm_source']);
        $this->assertSame('paid', $resolved['utm_medium']);
        $this->assertSame('latest-click', $resolved['gclid']);

        // …and first-touch is still preserved in full.
        $this->assertSame('google', $resolved['first_touch']['utm_source']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformedSnapshots(): array
    {
        return [
            'not json' => ['nonsense'],
            'empty string' => [''],
            'a json scalar' => ['"just a string"'],
            'an empty object' => ['{}'],
            'a json array' => ['[1,2,3]'],
        ];
    }

    #[DataProvider('malformedSnapshots')]
    public function test_a_malformed_snapshot_is_discarded_rather_than_stored(mixed $payload): void
    {
        $resolved = app(AttributionService::class)->resolve(
            Request::create('/contact', 'POST', ['latest_touch_snapshot' => $payload])
        );

        $this->assertNull($resolved['latest_touch']);
        $this->assertNull($resolved['utm_source']);
    }

    public function test_nested_and_non_scalar_values_are_dropped_from_a_snapshot(): void
    {
        // The snapshot is a hidden field, so it is entirely
        // client-controlled — anything that is not a flat map of scalars
        // must not reach the database.
        $resolved = app(AttributionService::class)->resolve(
            Request::create('/contact', 'POST', [
                'latest_touch_snapshot' => json_encode([
                    'utm_source' => 'google',
                    'nested' => ['a' => 'b'],
                    'listy' => [1, 2],
                ]),
            ])
        );

        $this->assertSame(['utm_source' => 'google'], $resolved['latest_touch']);
    }

    public function test_over_long_values_are_truncated_rather_than_breaking_the_insert(): void
    {
        $resolved = $this->resolve(['utm_source' => str_repeat('a', 900)]);

        $this->assertSame(255, mb_strlen($resolved['utm_source']));

        // …and the lead actually saves, which is the point.
        $lead = Lead::create(['full_name' => 'Test', 'type' => Lead::TYPE_CONTACT] + $resolved);
        $this->assertTrue($lead->exists);
    }

    // -----------------------------------------------------------------
    // source_platform resolution
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     */
    public static function sourceCasings(): array
    {
        return [
            'lowercase' => ['instagram', 'instagram'],
            'uppercase' => ['INSTAGRAM', 'instagram'],
            'mixed case' => ['InStaGram', 'instagram'],
            'another source' => ['GOOGLE', 'google'],
        ];
    }

    #[DataProvider('sourceCasings')]
    public function test_source_platform_resolves_case_insensitively_to_the_canonical_key(string $given, string $expected): void
    {
        // Otherwise "Instagram", "INSTAGRAM" and "instagram" would report
        // as three separate channels.
        $this->assertSame($expected, $this->resolve(['utm_source' => $given])['source_platform']);
    }

    public function test_an_unknown_source_is_kept_raw_rather_than_dropped(): void
    {
        $this->assertSame(0, LeadSource::where('key', 'some-newsletter')->count());

        // An unrecognised platform is still useful data, and dropping it
        // would make the lead look like it came from nowhere. That is why
        // leads.source_platform is a plain string, not a foreign key.
        $this->assertSame('some-newsletter', $this->resolve(['utm_source' => 'some-newsletter'])['source_platform']);
    }

    public function test_no_utm_source_means_no_source_platform(): void
    {
        $this->assertNull($this->resolve(['gclid' => 'abc'])['source_platform']);
        $this->assertNull($this->resolve(['utm_source' => '   '])['source_platform']);
    }

    // -----------------------------------------------------------------
    // Campaign linking
    // -----------------------------------------------------------------

    public function test_a_matching_external_id_links_the_campaign_without_touching_the_raw_string(): void
    {
        $campaign = Campaign::factory()->create([
            'external_campaign_id' => 'ext-9911',
            'name' => 'Spring Search',
        ]);

        $resolved = $this->resolve(['campaign_id' => 'ext-9911', 'utm_source' => 'google']);

        $this->assertSame($campaign->id, $resolved['linked_campaign_id']);
        $this->assertSame('Spring Search', $resolved['campaign_name']);

        // THE RAW STRING IS UNTOUCHED. campaign_id keeps exactly what the
        // ad platform sent; linked_campaign_id is the resolved FK. The two
        // are different identifier spaces and must not be collapsed.
        $this->assertSame('ext-9911', $resolved['campaign_id']);
    }

    public function test_an_unmatched_external_id_still_stores_the_raw_string(): void
    {
        $resolved = $this->resolve(['campaign_id' => 'ext-does-not-exist']);

        $this->assertNull($resolved['linked_campaign_id']);
        // A lead can legitimately carry an id for a campaign nobody has
        // created a record for yet — the attribution must not be lost.
        $this->assertSame('ext-does-not-exist', $resolved['campaign_id']);
    }

    public function test_utm_campaign_is_used_as_a_fallback_for_platforms_that_pass_only_a_name(): void
    {
        $campaign = Campaign::factory()->create(['external_campaign_id' => 'named-campaign']);

        $resolved = $this->resolve(['utm_campaign' => 'named-campaign']);

        $this->assertSame($campaign->id, $resolved['linked_campaign_id']);
        $this->assertNull($resolved['campaign_id'], 'The raw column must stay empty when no campaign_id was sent.');
    }

    public function test_campaign_id_wins_over_utm_campaign_when_both_match(): void
    {
        $byId = Campaign::factory()->create(['external_campaign_id' => 'by-id']);
        Campaign::factory()->create(['external_campaign_id' => 'by-name']);

        $resolved = $this->resolve(['campaign_id' => 'by-id', 'utm_campaign' => 'by-name']);

        $this->assertSame($byId->id, $resolved['linked_campaign_id']);
    }

    public function test_a_deleted_campaign_leaves_the_lead_intact(): void
    {
        $campaign = Campaign::factory()->create(['external_campaign_id' => 'ext-1']);

        $lead = Lead::create([
            'full_name' => 'Test',
            'type' => Lead::TYPE_CONTACT,
        ] + $this->resolve(['campaign_id' => 'ext-1']));

        $campaign->delete();

        // nullOnDelete: the enquiry survives, and the raw external id it
        // arrived with is still there.
        $this->assertNull($lead->fresh()->linked_campaign_id);
        $this->assertSame('ext-1', $lead->fresh()->campaign_id);
    }

    // -----------------------------------------------------------------
    // End to end through a real submission
    // -----------------------------------------------------------------

    public function test_a_real_submission_stores_the_full_attribution_set(): void
    {
        $campaign = Campaign::factory()->create(['external_campaign_id' => 'camp-77']);

        $this->post(route('contact.store'), [
            'full_name' => 'نورة',
            'email' => 'noura@example.test',
            'message' => 'مرحبا',
            'consent_given' => '1',
            'latest_touch_snapshot' => json_encode([
                'utm_source' => 'INSTAGRAM',
                'utm_medium' => 'paid_social',
                'utm_campaign' => 'launch',
                'campaign_id' => 'camp-77',
                'adset_id' => 'as-1',
                'ad_id' => 'ad-1',
                'fbclid' => 'fb-xyz',
                'landing_page' => '/packages',
                'referrer' => 'https://instagram.com/',
            ]),
            'first_touch_snapshot' => json_encode(['utm_source' => 'google', 'landing_page' => '/']),
        ])->assertRedirect();

        $lead = Lead::latest('id')->firstOrFail();

        $this->assertSame('instagram', $lead->source_platform);
        $this->assertSame('paid_social', $lead->utm_medium);
        $this->assertSame('camp-77', $lead->campaign_id);
        $this->assertSame($campaign->id, $lead->linked_campaign_id);
        $this->assertSame('fb-xyz', $lead->fbclid);
        $this->assertSame('/packages', $lead->landing_page_url);
        $this->assertSame('https://instagram.com/', $lead->referrer_url);
        $this->assertSame('google', $lead->first_touch['utm_source']);
    }

    public function test_a_submission_with_no_attribution_still_creates_the_lead(): void
    {
        $this->post(route('contact.store'), [
            'full_name' => 'Direct Visitor',
            'email' => 'direct@example.test',
            'message' => 'Hello',
            'consent_given' => '1',
        ])->assertRedirect();

        $lead = Lead::latest('id')->firstOrFail();

        // Attribution is nice to have; the enquiry is the point.
        $this->assertNull($lead->source_platform);
        $this->assertNull($lead->first_touch);
        $this->assertSame('Direct Visitor', $lead->full_name);
    }
}
