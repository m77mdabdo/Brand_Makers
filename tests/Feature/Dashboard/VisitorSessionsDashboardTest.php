<?php

namespace Tests\Feature\Dashboard;

use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Models\VisitorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VisitorSessionsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::factory()->admin()->create();
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
            'index' => ['dashboard.visitor-sessions.index', false],
            'show' => ['dashboard.visitor-sessions.show', true],
            'filtered export' => ['dashboard.visitor-sessions.export', false],
            'single export' => ['dashboard.visitor-sessions.export-one', true],
        ];
    }

    /**
     * One test per screen rather than a loop: actingAs() authenticates for
     * the REST of the test, so a loop checking guest → non-admin → admin
     * would still be signed in on its second pass and the guest assertion
     * would pass vacuously.
     */
    #[DataProvider('guardedScreens')]
    public function test_the_screen_redirects_a_guest(string $route, bool $needsSession): void
    {
        $url = $needsSession ? route($route, VisitorSession::factory()->create()) : route($route);

        $this->get($url)->assertRedirect(route('login'));
    }

    #[DataProvider('guardedScreens')]
    public function test_the_screen_is_forbidden_to_a_non_admin(string $route, bool $needsSession): void
    {
        $url = $needsSession ? route($route, VisitorSession::factory()->create()) : route($route);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    #[DataProvider('guardedScreens')]
    public function test_an_admin_reaches_the_screen(string $route, bool $needsSession): void
    {
        $url = $needsSession ? route($route, VisitorSession::factory()->create()) : route($route);

        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    public function test_the_sidebar_links_to_the_section(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertSee(route('dashboard.visitor-sessions.index'), false);
    }

    // -----------------------------------------------------------------
    // Index
    // -----------------------------------------------------------------

    public function test_the_index_tells_an_admin_when_recording_is_switched_off(): void
    {
        Setting::updateOrCreate(['key' => 'visitor_sessions_enabled'], ['value' => false, 'group' => 'visitor_sessions']);

        // An empty table with no explanation reads as a broken feature.
        $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.index'))
            ->assertSee(__('dashboard.visitor_sessions.disabled_notice'), false);
    }

    public function test_the_index_states_the_retention_window(): void
    {
        Setting::updateOrCreate(['key' => 'visitor_sessions_retention_days'], ['value' => 45, 'group' => 'visitor_sessions']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.index'))
            // Escaped, not raw: the notice contains quotation marks
            // around the DNT signal name, which Blade renders as &quot;.
            ->assertSee(__('dashboard.visitor_sessions.consent_notice', ['days' => 45]));
    }

    public function test_the_index_filters_by_conversion(): void
    {
        VisitorSession::factory()->create(['entry_url' => '/converted-entry', 'lead_id' => Lead::factory()]);
        VisitorSession::factory()->create(['entry_url' => '/browsing-entry', 'lead_id' => null]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.index', ['converted' => 'converted']))
            ->assertSee('/converted-entry', false)
            ->assertDontSee('/browsing-entry', false);

        $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.index', ['converted' => 'not_converted']))
            ->assertSee('/browsing-entry', false)
            ->assertDontSee('/converted-entry', false);
    }

    public function test_the_index_filters_by_source(): void
    {
        VisitorSession::factory()->create([
            'entry_url' => '/from-google',
            'attribution' => ['latest_touch' => ['utm_source' => 'google']],
        ]);
        VisitorSession::factory()->create([
            'entry_url' => '/from-instagram',
            'attribution' => ['latest_touch' => ['utm_source' => 'instagram']],
        ]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.index', ['source' => 'google']))
            ->assertSee('/from-google', false)
            ->assertDontSee('/from-instagram', false);
    }

    public function test_the_index_filters_by_device(): void
    {
        VisitorSession::factory()->create(['entry_url' => '/on-desktop']);
        VisitorSession::factory()->onMobile()->create(['entry_url' => '/on-mobile']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.index', ['device' => 'mobile']))
            ->assertSee('/on-mobile', false)
            ->assertDontSee('/on-desktop', false);
    }

    public function test_the_index_filters_by_an_inclusive_date_range(): void
    {
        VisitorSession::factory()->create(['entry_url' => '/today-entry', 'started_at' => now()]);
        VisitorSession::factory()->create(['entry_url' => '/old-entry', 'started_at' => now()->subMonth()]);

        $this->actingAs($this->admin)->get(route('dashboard.visitor-sessions.index', [
            'date_from' => now()->toDateString(),
            // whereDate, so "to today" includes everything today rather
            // than stopping at midnight.
            'date_to' => now()->toDateString(),
        ]))
            ->assertSee('/today-entry', false)
            ->assertDontSee('/old-entry', false);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function junkFilters(): array
    {
        return [
            'an unknown conversion state' => [['converted' => 'maybe']],
            'an unknown device' => [['device' => 'fridge']],
            'a date that is not a date' => [['date_from' => 'yesterday']],
            // Shaped like a date but not one — these used to survive the
            // whitelist and silently empty the table. See CalendarDate.
            'a shaped-but-impossible date' => [['date_from' => '2026-13-45']],
            'a shaped-but-impossible date_to' => [['date_to' => '2026-13-45']],
            'a date that rolls over' => [['date_from' => '2026-02-31']],
            'a zero month' => [['date_from' => '2026-00-10']],
            'an injection attempt in a date' => [['date_to' => "2020-01-01' OR 1=1--"]],
        ];
    }

    /**
     * @param  array<string, string>  $filter
     */
    #[DataProvider('junkFilters')]
    public function test_a_junk_filter_is_dropped_rather_than_emptying_the_table(array $filter): void
    {
        VisitorSession::factory()->count(3)->create();

        // A bad filter returning nothing would read as "no sessions" and
        // hide the real data.
        $response = $this->actingAs($this->admin)->get(route('dashboard.visitor-sessions.index', $filter));

        $response->assertOk();
        $this->assertCount(3, $response->viewData('sessions'));
    }

    public function test_a_malformed_date_neither_errors_nor_hides_the_table(): void
    {
        VisitorSession::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.index', ['date_from' => '2026-13-45', 'date_to' => '2026-02-31']));

        $response->assertOk();
        $this->assertCount(3, $response->viewData('sessions'));
    }

    public function test_filters_survive_pagination(): void
    {
        VisitorSession::factory()->count(30)->onMobile()->create();

        $response = $this->actingAs($this->admin)->get(route('dashboard.visitor-sessions.index', ['device' => 'mobile']));

        $this->assertStringContainsString('device=mobile', $response->viewData('sessions')->nextPageUrl());
    }

    // -----------------------------------------------------------------
    // Show
    // -----------------------------------------------------------------

    public function test_the_timeline_shows_the_journey_in_order(): void
    {
        $session = VisitorSession::factory()->create([
            'events' => [
                ['type' => VisitorSession::TYPE_PAGEVIEW, 'url' => '/first-page', 'label' => null, 'ts' => now()->subMinutes(3)->toIso8601String(), 'duration_ms' => 4200],
                ['type' => VisitorSession::TYPE_PAGEVIEW, 'url' => '/second-page', 'label' => null, 'ts' => now()->subMinutes(2)->toIso8601String(), 'duration_ms' => null],
                ['type' => VisitorSession::TYPE_ACTION, 'url' => '/second-page', 'label' => 'contact_submitted', 'ts' => now()->toIso8601String(), 'duration_ms' => null],
            ],
        ]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.show', $session))
            ->assertSeeInOrder(['/first-page', '/second-page'], false)
            ->assertSee(__('dashboard.visitor_sessions.labels.contact_submitted'), false)
            ->assertSee('4.2s', false);
    }

    public function test_a_malformed_event_degrades_to_a_shorter_timeline_rather_than_a_500(): void
    {
        $session = VisitorSession::factory()->create([
            'events' => [
                ['type' => VisitorSession::TYPE_PAGEVIEW, 'url' => '/good-page', 'label' => null, 'ts' => now()->toIso8601String(), 'duration_ms' => null],
                ['nonsense' => true],
                'not even an array',
            ],
        ]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.show', $session))
            ->assertOk()
            ->assertSee('/good-page', false);
    }

    public function test_a_truncated_journey_says_so(): void
    {
        $session = VisitorSession::factory()->truncated()->create();

        $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.show', $session))
            ->assertSee(__('dashboard.visitor_sessions.journey_truncated', ['limit' => VisitorSession::MAX_EVENTS]), false);
    }

    public function test_the_detail_screen_never_prints_the_visitors_contact_details(): void
    {
        $lead = Lead::factory()->create([
            'full_name' => 'Noura Contact',
            'email' => 'noura@example.test',
            'phone' => '+966500000000',
        ]);

        $session = VisitorSession::factory()->create(['lead_id' => $lead->id]);

        $content = $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.show', $session))
            ->getContent();

        // This screen is about the JOURNEY. The Leads section is where the
        // person behind it is looked up.
        $this->assertStringNotContainsString('Noura Contact', $content);
        $this->assertStringNotContainsString('noura@example.test', $content);
        $this->assertStringNotContainsString('+966500000000', $content);

        // …but it does link to the enquiry.
        $this->assertStringContainsString(route('dashboard.leads.show', $lead), $content);
    }

    public function test_the_detail_screen_shows_the_truncated_ip_and_says_it_is_truncated(): void
    {
        $session = VisitorSession::factory()->create(['ip_prefix' => '203.0.113.44']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.show', $session))
            ->assertSee('203.0.113.0', false)
            ->assertSee(__('dashboard.visitor_sessions.ip_prefix_hint'), false);
    }

    // -----------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------

    public function test_a_single_session_export_has_the_documented_shape(): void
    {
        $session = VisitorSession::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('dashboard.visitor-sessions.export-one', $session));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="'.$this->filenameFor($response).'"');

        $payload = $response->json();

        $this->assertSame(1, $payload['session_count']);
        $this->assertArrayHasKey('exported_at', $payload);

        $exported = $payload['sessions'][0];

        foreach ([
            'id', 'session_uid', 'started_at', 'last_activity_at', 'total_duration_seconds',
            'entry_url', 'referrer', 'page_count', 'journey_truncated', 'journey_event_limit',
            'device', 'location', 'attribution', 'converted', 'lead', 'journey',
        ] as $key) {
            $this->assertArrayHasKey($key, $exported, "The export is an interface; [{$key}] must not disappear from it.");
        }

        // Named ip_prefix, not ip: it is a /24 or /48, never an address.
        $this->assertArrayHasKey('ip_prefix', $exported['location']);
        $this->assertArrayNotHasKey('ip', $exported['location']);
        $this->assertSame(VisitorSession::MAX_EVENTS, $exported['journey_event_limit']);
    }

    private function filenameFor(TestResponse $response): string
    {
        preg_match('/filename="([^"]+)"/', $response->headers->get('Content-Disposition'), $matches);

        return $matches[1];
    }

    public function test_the_export_carries_the_leads_id_type_and_date_but_never_its_pii(): void
    {
        $lead = Lead::factory()->create([
            'full_name' => 'Noura Exported',
            'email' => 'noura@example.test',
            'phone' => '+966500000000',
            'type' => Lead::TYPE_CONTACT,
        ]);

        $session = VisitorSession::factory()->create(['lead_id' => $lead->id]);

        $response = $this->actingAs($this->admin)->get(route('dashboard.visitor-sessions.export-one', $session));

        $exportedLead = $response->json('sessions.0.lead');

        $this->assertSame(['id', 'type', 'created_at'], array_keys($exportedLead));
        $this->assertSame($lead->id, $exportedLead['id']);
        $this->assertSame(Lead::TYPE_CONTACT, $exportedLead['type']);

        // Against the WHOLE file, not just the lead node: a journey file
        // that quietly carried contact data is exactly what ends up
        // forwarded outside the business.
        $body = $response->getContent();
        $this->assertStringNotContainsString('Noura Exported', $body);
        $this->assertStringNotContainsString('noura@example.test', $body);
        $this->assertStringNotContainsString('+966500000000', $body);
    }

    public function test_an_unconverted_session_exports_a_null_lead(): void
    {
        $session = VisitorSession::factory()->create(['lead_id' => null]);

        $response = $this->actingAs($this->admin)->get(route('dashboard.visitor-sessions.export-one', $session));

        $this->assertNull($response->json('sessions.0.lead'));
        $this->assertFalse($response->json('sessions.0.converted'));
    }

    public function test_the_filtered_export_matches_the_table_it_came_from(): void
    {
        VisitorSession::factory()->count(2)->onMobile()->create();
        VisitorSession::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard.visitor-sessions.export', ['device' => 'mobile']));

        $this->assertSame(2, $response->json('session_count'));
        $this->assertSame(['device' => 'mobile'], $response->json('filters'));
    }

    public function test_an_unfiltered_export_carries_everything(): void
    {
        VisitorSession::factory()->count(4)->create();

        $response = $this->actingAs($this->admin)->get(route('dashboard.visitor-sessions.export'));

        $this->assertSame(4, $response->json('session_count'));
        // (object) cast, so an empty filter set encodes as {} and a
        // consumer expecting a map does not choke on [].
        $this->assertSame([], $response->json('filters'));
    }

    public function test_the_export_is_readable_arabic_rather_than_escaped_unicode(): void
    {
        VisitorSession::factory()->create([
            'attribution' => ['latest_touch' => ['utm_campaign' => 'حملة-الربيع']],
        ]);

        $body = $this->actingAs($this->admin)->get(route('dashboard.visitor-sessions.export'))->getContent();

        // A file a person opens and reads, not a wire format.
        $this->assertStringContainsString('حملة-الربيع', $body);
        // …and NOT as \u062d-style escapes, which would make an export of
        // an Arabic-language site unreadable in a text editor.
        $this->assertStringNotContainsString('\\u06', $body);
    }
}
