<?php

namespace Tests\Feature\Dashboard;

use App\Models\Article;
use App\Models\Campaign;
use App\Models\Comment;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadsAndInboxTest extends TestCase
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
            'leads index' => ['dashboard.leads.index', false],
            'lead show' => ['dashboard.leads.show', true],
            'contact inbox' => ['dashboard.contact-messages.index', false],
        ];
    }

    /**
     * One test per screen rather than a loop over three.
     *
     * actingAs() authenticates for the REST of the test, so a loop that
     * checks guest → non-admin → admin would silently still be signed in
     * as the admin on its second pass, and the guest assertion would pass
     * vacuously. A data provider gives each screen its own fresh state.
     */
    #[DataProvider('guardedScreens')]
    public function test_the_screen_is_admin_only(string $route, bool $needsLead): void
    {
        $url = $needsLead
            ? route($route, Lead::factory()->create())
            : route($route);

        $this->get($url)->assertRedirect(route('login'));
    }

    #[DataProvider('guardedScreens')]
    public function test_a_non_admin_is_forbidden_from_the_screen(string $route, bool $needsLead): void
    {
        $url = $needsLead ? route($route, Lead::factory()->create()) : route($route);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    #[DataProvider('guardedScreens')]
    public function test_an_admin_reaches_the_screen(string $route, bool $needsLead): void
    {
        $url = $needsLead ? route($route, Lead::factory()->create()) : route($route);

        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    // -----------------------------------------------------------------
    // Filters
    // -----------------------------------------------------------------

    public function test_the_index_filters_by_type(): void
    {
        Lead::factory()->create(['type' => Lead::TYPE_CONTACT, 'full_name' => 'Contact Person']);
        Lead::factory()->create(['type' => Lead::TYPE_PACKAGE_ENQUIRY, 'full_name' => 'Package Person']);

        $response = $this->actingAs($this->admin)->get(route('dashboard.leads.index', ['type' => Lead::TYPE_CONTACT]));

        $response->assertSee('Contact Person', false);
        $response->assertDontSee('Package Person', false);
    }

    public function test_an_unknown_type_filter_shows_everything_rather_than_an_empty_queue(): void
    {
        Lead::factory()->count(3)->create();

        // A bad filter returning nothing would read as "no leads" and hide
        // the real queue.
        $response = $this->actingAs($this->admin)->get(route('dashboard.leads.index', ['type' => 'nonsense']));

        $this->assertCount(3, $response->viewData('leads'));
    }

    public function test_the_index_filters_by_source_platform(): void
    {
        Lead::factory()->create(['source_platform' => 'instagram', 'full_name' => 'From Instagram']);
        Lead::factory()->create(['source_platform' => 'behance', 'full_name' => 'From Behance']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.leads.index', ['source_platform' => 'instagram']))
            ->assertSee('From Instagram', false)
            ->assertDontSee('From Behance', false);
    }

    public function test_the_index_filters_by_service_and_by_package(): void
    {
        $service = Service::active()->firstOrFail();
        $package = Package::active()->firstOrFail();

        Lead::factory()->create(['requested_service_id' => $service->id, 'full_name' => 'Service Lead']);
        Lead::factory()->create(['package_id' => $package->id, 'full_name' => 'Package Lead']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.leads.index', ['requested_service_id' => $service->id]))
            ->assertSee('Service Lead', false)
            ->assertDontSee('Package Lead', false);

        $this->actingAs($this->admin)
            ->get(route('dashboard.leads.index', ['package_id' => $package->id]))
            ->assertSee('Package Lead', false)
            ->assertDontSee('Service Lead', false);
    }

    public function test_the_index_filters_by_an_inclusive_date_range(): void
    {
        $old = Lead::factory()->create(['full_name' => 'Old Lead']);
        $old->forceFill(['created_at' => now()->subMonth()])->save();

        $today = Lead::factory()->create(['full_name' => 'Today Lead']);

        $response = $this->actingAs($this->admin)->get(route('dashboard.leads.index', [
            'date_from' => now()->toDateString(),
            // whereDate, so "to today" includes everything today rather
            // than stopping at midnight.
            'date_to' => now()->toDateString(),
        ]));

        $response->assertSee('Today Lead', false);
        $response->assertDontSee('Old Lead', false);
    }

    public function test_filters_survive_pagination(): void
    {
        Lead::factory()->count(25)->create(['type' => Lead::TYPE_CONTACT]);

        $response = $this->actingAs($this->admin)->get(route('dashboard.leads.index', ['type' => Lead::TYPE_CONTACT]));

        // withQueryString() on the paginator — otherwise page 2 silently
        // drops the filter.
        $this->assertStringContainsString('type='.Lead::TYPE_CONTACT, $response->viewData('leads')->nextPageUrl());
    }

    // -----------------------------------------------------------------
    // Show
    // -----------------------------------------------------------------

    public function test_the_show_screen_displays_the_attribution_and_both_snapshots(): void
    {
        $campaign = Campaign::factory()->create(['name' => 'Spring Search', 'external_campaign_id' => 'ext-1']);

        $lead = Lead::factory()->create([
            'source_platform' => 'google',
            'utm_campaign' => 'spring',
            'campaign_id' => 'ext-1',
            'linked_campaign_id' => $campaign->id,
            'first_touch' => ['utm_source' => 'google'],
            'latest_touch' => ['utm_source' => 'instagram'],
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard.leads.show', $lead));

        $response->assertOk();
        $response->assertSee('google', false);
        $response->assertSee('Spring Search', false);
        $response->assertSee('ext-1', false);
        $response->assertSee(__('dashboard.leads.snapshots'), false);
    }

    public function test_the_show_screen_escapes_the_visitor_supplied_message(): void
    {
        $lead = Lead::factory()->create(['message' => 'Hi <script>alert(1)</script>']);

        $content = $this->actingAs($this->admin)->get(route('dashboard.leads.show', $lead))->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    public function test_archiving_a_lead_soft_deletes_it(): void
    {
        $lead = Lead::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('dashboard.leads.destroy', $lead))
            ->assertRedirect(route('dashboard.leads.index'));

        // An enquiry is a historical record — the conversion events and
        // journeys attached to it keep resolving withTrashed().
        $this->assertSoftDeleted($lead);
    }

    // -----------------------------------------------------------------
    // Contact inbox
    // -----------------------------------------------------------------

    public function test_the_inbox_shows_only_contact_leads(): void
    {
        Lead::factory()->contact()->create(['full_name' => 'Contact Person']);
        Lead::factory()->create(['type' => Lead::TYPE_PACKAGE_ENQUIRY, 'full_name' => 'Package Person']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.contact-messages.index'))
            ->assertSee('Contact Person', false)
            ->assertDontSee('Package Person', false);
    }

    public function test_the_inbox_puts_unhandled_messages_first(): void
    {
        Lead::factory()->contact()->handled()->create(['full_name' => 'Already Handled']);
        Lead::factory()->contact()->create(['full_name' => 'Still Unread']);

        // The inbox is a work queue, not a chronological log — the older
        // unread message outranks the newer handled one.
        $this->actingAs($this->admin)
            ->get(route('dashboard.contact-messages.index'))
            ->assertSeeInOrder(['Still Unread', 'Already Handled'], false);
    }

    public function test_marking_a_message_handled_round_trips(): void
    {
        $lead = Lead::factory()->contact()->create();

        $this->actingAs($this->admin)
            ->patch(route('dashboard.contact-messages.handled', $lead))
            ->assertRedirect();

        $this->assertTrue($lead->fresh()->is_handled);

        $this->actingAs($this->admin)->patch(route('dashboard.contact-messages.handled', $lead));

        $this->assertFalse($lead->fresh()->is_handled);
    }

    public function test_the_handled_endpoint_refuses_a_lead_that_is_not_a_contact_message(): void
    {
        $lead = Lead::factory()->create(['type' => Lead::TYPE_PACKAGE_ENQUIRY]);

        // Binding by id alone would let this flip is_handled on ANY lead
        // from a URL guessed off this screen.
        $this->actingAs($this->admin)
            ->patch(route('dashboard.contact-messages.handled', $lead))
            ->assertNotFound();

        $this->assertFalse($lead->fresh()->is_handled);
    }

    public function test_the_inbox_filters_by_handled_state(): void
    {
        Lead::factory()->contact()->create(['full_name' => 'Unread One']);
        Lead::factory()->contact()->handled()->create(['full_name' => 'Handled One']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.contact-messages.index', ['filter' => 'unhandled']))
            ->assertSee('Unread One', false)
            ->assertDontSee('Handled One', false);

        $this->actingAs($this->admin)
            ->get(route('dashboard.contact-messages.index', ['filter' => 'handled']))
            ->assertSee('Handled One', false)
            ->assertDontSee('Unread One', false);
    }

    // -----------------------------------------------------------------
    // The unread badge
    // -----------------------------------------------------------------

    public function test_the_sidebar_badge_counts_unhandled_contact_messages_on_every_screen(): void
    {
        Lead::factory()->contact()->count(3)->create();
        Lead::factory()->contact()->handled()->create();
        // Not a contact message, so it must not be counted.
        Lead::factory()->create(['type' => Lead::TYPE_PACKAGE_ENQUIRY]);

        foreach ([route('dashboard'), route('dashboard.services.index')] as $url) {
            $this->actingAs($this->admin)->get($url)->assertSee('>3', false);
        }
    }

    public function test_the_badge_disappears_when_the_inbox_is_clear(): void
    {
        Lead::factory()->contact()->handled()->create();

        // Rendered only when there IS something unread — the sidebar is
        // not permanently decorated with a zero.
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertDontSee(__('dashboard.resources.unhandled'), false);
    }

    // -----------------------------------------------------------------
    // Comment moderation still works
    // -----------------------------------------------------------------

    public function test_comment_moderation_still_works_end_to_end(): void
    {
        $article = Article::live()->firstOrFail();

        $this->post(route('articles.comments.store', $article), [
            'name' => 'Reader', 'email' => 'reader@example.test', 'body' => 'A public comment',
        ]);

        $comment = Comment::latest('id')->firstOrFail();
        $this->assertSame(Comment::STATUS_PENDING, $comment->status);

        $this->actingAs($this->admin)
            ->get(route('dashboard.comments.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('A public comment', false);

        $this->actingAs($this->admin)->patch(route('dashboard.comments.approve', $comment))->assertRedirect();

        $this->assertSame(Comment::STATUS_APPROVED, $comment->fresh()->status);
        $this->get(route('articles.show', $article))->assertSee('A public comment', false);
    }
}
