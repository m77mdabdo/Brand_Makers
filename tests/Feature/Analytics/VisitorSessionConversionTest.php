<?php

namespace Tests\Feature\Analytics;

use App\Models\Article;
use App\Models\Lead;
use App\Models\Package;
use App\Models\VisitorSession;
use App\Services\Analytics\VisitorSessionService;
use App\Services\Analytics\VisitorTrackingGate;
use App\Support\Honeypot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Linking a journey to the enquiry it produced.
 */
class VisitorSessionConversionTest extends TestCase
{
    use RefreshDatabase;

    private VisitorSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->session = VisitorSession::factory()->create(['lead_id' => null]);
    }

    /**
     * A consenting visitor carrying the session cookie a real browser
     * would send back — encrypted, because cm_session is not excepted.
     */
    private function consenting(): static
    {
        return $this
            ->withUnencryptedCookie(VisitorTrackingGate::CONSENT_COOKIE, VisitorTrackingGate::CONSENT_GRANTED)
            ->withCookie(VisitorSessionService::COOKIE, $this->session->session_uid)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0 Safari/537.36');
    }

    // -----------------------------------------------------------------
    // Contact
    // -----------------------------------------------------------------

    public function test_a_contact_submission_links_the_session_and_records_the_action(): void
    {
        $this->consenting()->post(route('contact.store'), [
            'full_name' => 'نورة', 'email' => 'noura@example.test', 'message' => 'مرحبا', 'consent_given' => '1',
        ])->assertRedirect();

        $lead = Lead::latest('id')->firstOrFail();
        $session = $this->session->fresh();

        $this->assertSame($lead->id, $session->lead_id);
        $this->assertTrue($session->hasConverted());

        // Assigned to a local first: end() takes its argument by
        // reference, and $session->events is an accessor, not a property.
        $events = $session->events;
        $last = end($events);
        $this->assertSame(VisitorSession::TYPE_ACTION, $last['type']);
        $this->assertSame('contact_submitted', $last['label']);
    }

    public function test_a_conversion_action_does_not_count_as_a_page_view(): void
    {
        $before = $this->session->page_count;

        $this->consenting()->post(route('contact.store'), [
            'full_name' => 'نورة', 'email' => 'noura@example.test', 'message' => 'مرحبا', 'consent_given' => '1',
        ]);

        // page_count stays a count of PAGES; the action is on the
        // timeline, not in the total.
        $this->assertSame($before, $this->session->fresh()->page_count);
    }

    // -----------------------------------------------------------------
    // Package enquiry
    // -----------------------------------------------------------------

    public function test_a_package_enquiry_links_the_session(): void
    {
        $package = Package::active()->firstOrFail();

        $this->consenting()->post(route('packages.enquire'), [
            'package_id' => $package->id,
            'full_name' => 'سارة', 'email' => 'sara@example.test', 'consent_given' => '1',
        ])->assertRedirect();

        $lead = Lead::latest('id')->firstOrFail();

        $this->assertSame(Lead::TYPE_PACKAGE_ENQUIRY, $lead->type);
        $this->assertSame($lead->id, $this->session->fresh()->lead_id);
    }

    // -----------------------------------------------------------------
    // Comments
    // -----------------------------------------------------------------

    public function test_a_comment_is_an_action_but_never_a_conversion(): void
    {
        $article = Article::live()->firstOrFail();

        $this->consenting()->post(route('articles.comments.store', $article), [
            'name' => 'قارئ', 'email' => 'reader@example.test', 'body' => 'تعليق',
        ])->assertRedirect();

        $session = $this->session->fresh();

        // Engagement, not a conversion — linking it would inflate every
        // conversion figure the reports compute.
        $this->assertNull($session->lead_id);
        $this->assertFalse($session->hasConverted());
        $events = $session->events;
        $this->assertSame('comment_submitted', end($events)['label']);
    }

    // -----------------------------------------------------------------
    // Refusals and idempotency
    // -----------------------------------------------------------------

    public function test_a_submission_without_consent_creates_the_lead_but_no_link(): void
    {
        $this->withCookie(VisitorSessionService::COOKIE, $this->session->session_uid)
            ->post(route('contact.store'), [
                'full_name' => 'زائر', 'email' => 'v@example.test', 'message' => 'مرحبا', 'consent_given' => '1',
            ])->assertRedirect();

        // The enquiry is the point; the journey is a nice-to-have.
        $this->assertSame(1, Lead::count());
        $this->assertNull($this->session->fresh()->lead_id);
    }

    public function test_a_privacy_signal_prevents_the_link(): void
    {
        $this->consenting()->withHeader('DNT', '1')->post(route('contact.store'), [
            'full_name' => 'زائر', 'email' => 'v@example.test', 'message' => 'مرحبا', 'consent_given' => '1',
        ])->assertRedirect();

        $this->assertSame(1, Lead::count());
        $this->assertNull($this->session->fresh()->lead_id);
    }

    public function test_a_submission_with_no_session_cookie_still_creates_the_lead(): void
    {
        // A visitor whose first ever request is the form POST itself,
        // which the middleware never saw because it only tracks GETs.
        $this->withUnencryptedCookie(VisitorTrackingGate::CONSENT_COOKIE, VisitorTrackingGate::CONSENT_GRANTED)
            ->post(route('contact.store'), [
                'full_name' => 'زائر', 'email' => 'v@example.test', 'message' => 'مرحبا', 'consent_given' => '1',
            ])->assertRedirect();

        $this->assertSame(1, Lead::count());
        $this->assertNull($this->session->fresh()->lead_id);
    }

    public function test_a_second_submission_does_not_repoint_the_journey(): void
    {
        foreach (['first@example.test', 'second@example.test'] as $email) {
            $this->consenting()->post(route('contact.store'), [
                'full_name' => 'زائر', 'email' => $email, 'message' => 'مرحبا', 'consent_given' => '1',
            ]);
        }

        // The journey belongs to the conversion that ENDED it; silently
        // repointing it would lose that.
        $this->assertSame(
            Lead::where('email', 'first@example.test')->sole()->id,
            $this->session->fresh()->lead_id,
        );
    }

    public function test_a_honeypot_submission_writes_nothing_at_all(): void
    {
        $before = count($this->session->events);

        $this->consenting()->post(route('contact.store'), [
            'full_name' => 'Bot', 'email' => 'bot@example.test', 'message' => 'spam', 'consent_given' => '1',
            Honeypot::FIELD => 'https://spam.example',
        ])->assertRedirect();

        $this->assertSame(0, Lead::count());
        // The honeypot check runs before ANY write, including the journey.
        $this->assertCount($before, $this->session->fresh()->events);
        $this->assertNull($this->session->fresh()->lead_id);
    }

    // -----------------------------------------------------------------
    // The link survives archiving
    // -----------------------------------------------------------------

    public function test_a_journey_attached_to_an_archived_lead_still_reads_as_converted(): void
    {
        $this->consenting()->post(route('contact.store'), [
            'full_name' => 'نورة', 'email' => 'noura@example.test', 'message' => 'مرحبا', 'consent_given' => '1',
        ]);

        Lead::latest('id')->firstOrFail()->delete();

        $session = $this->session->fresh();

        // withTrashed() on the relation: a converted session that suddenly
        // read as unconverted would be a silently wrong analytics number.
        $this->assertTrue($session->hasConverted());
        $this->assertNotNull($session->lead);
    }
}
