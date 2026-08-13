<?php

namespace Tests\Feature\Public;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Lead;
use App\Models\Package;
use App\Support\Honeypot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicFormsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        RateLimiter::clear('');
    }

    /**
     * @return array<string, mixed>
     */
    private function contactPayload(array $overrides = []): array
    {
        return array_replace([
            'full_name' => 'نورة العتيبي',
            'email' => 'noura@example.test',
            'phone' => '+966500000000',
            'message' => 'استفسار عن الهوية البصرية',
            'consent_given' => '1',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Contact form
    // -----------------------------------------------------------------

    public function test_it_creates_a_contact_lead(): void
    {
        $this->post(route('contact.store'), $this->contactPayload())
            ->assertRedirect()
            ->assertSessionHas('status', __('site.flash.contact_submitted'));

        $lead = Lead::latest('id')->firstOrFail();

        $this->assertSame(Lead::TYPE_CONTACT, $lead->type);
        $this->assertSame('noura@example.test', $lead->email);
        $this->assertTrue($lead->consent_given);
        $this->assertNotNull($lead->consented_at);
        // is_handled is guarded, so a fresh enquiry always lands unread in
        // the inbox regardless of what the payload said.
        $this->assertFalse($lead->is_handled);
    }

    public function test_a_submission_cannot_mark_its_own_enquiry_as_handled(): void
    {
        $this->post(route('contact.store'), $this->contactPayload(['is_handled' => '1']));

        $this->assertFalse(Lead::latest('id')->firstOrFail()->is_handled);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidContactPayloads(): array
    {
        return [
            'no name' => [['full_name' => ''], 'full_name'],
            'no email' => [['email' => ''], 'email'],
            'bad email' => [['email' => 'not-an-email'], 'email'],
            'no message' => [['message' => ''], 'message'],
            'no consent' => [['consent_given' => null], 'consent_given'],
            'junk phone' => [['phone' => 'call me maybe'], 'phone'],
        ];
    }

    #[DataProvider('invalidContactPayloads')]
    public function test_an_invalid_contact_submission_is_rejected(array $overrides, string $field): void
    {
        $this->post(route('contact.store'), $this->contactPayload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Lead::count());
    }

    public function test_consent_must_be_genuinely_accepted_not_merely_present(): void
    {
        // `accepted`, not `boolean` — consent has to be provable per lead.
        $this->post(route('contact.store'), $this->contactPayload(['consent_given' => '0']))
            ->assertSessionHasErrors('consent_given');

        $this->assertSame(0, Lead::count());
    }

    // -----------------------------------------------------------------
    // Honeypot
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function honeypotForms(): array
    {
        return [
            'contact' => ['contact.store', [
                'full_name' => 'Bot', 'email' => 'bot@example.test',
                'message' => 'spam', 'consent_given' => '1',
            ]],
        ];
    }

    #[DataProvider('honeypotForms')]
    public function test_a_tripped_honeypot_writes_nothing_and_looks_identical_to_a_real_submit(string $route, array $payload): void
    {
        $clean = $this->post(route($route), $payload);
        $baseline = Lead::count();

        $trapped = $this->post(route($route), $payload + [Honeypot::FIELD => 'http://spam.test']);

        // Byte-for-byte the same outcome: same status, same destination,
        // same flash. Anything else tells a bot author which field to
        // leave alone next time.
        $this->assertSame($clean->getStatusCode(), $trapped->getStatusCode());
        $this->assertSame($clean->headers->get('Location'), $trapped->headers->get('Location'));
        $trapped->assertSessionHas('status', __('site.flash.contact_submitted'));

        // …and nothing was written.
        $this->assertSame($baseline, Lead::count());
    }

    public function test_the_honeypot_is_not_validated_so_a_bot_learns_nothing_from_the_response(): void
    {
        // A `prohibited` rule would return 422 naming the field.
        $this->post(route('contact.store'), $this->contactPayload([Honeypot::FIELD => 'anything']))
            ->assertSessionHasNoErrors();
    }

    public function test_the_honeypot_is_hidden_from_sight_and_from_assistive_tech(): void
    {
        $content = $this->get(route('contact'))->getContent();

        $this->assertStringContainsString('name="'.Honeypot::FIELD.'"', $content);
        // aria-hidden + tabindex="-1" + off-screen, so no human is ever
        // offered it, and autocomplete="off" stops a password manager
        // filling it in and silently discarding a real enquiry.
        $this->assertMatchesRegularExpression('/aria-hidden="true"[^>]*>\s*<label[^>]*>[^<]*<\/label>\s*<input[^>]*tabindex="-1"/s', $content);
        $this->assertStringContainsString('autocomplete="off"', $content);
    }

    // -----------------------------------------------------------------
    // Throttling
    // -----------------------------------------------------------------

    public function test_public_writes_are_throttled(): void
    {
        $lastStatus = 200;

        for ($i = 0; $i < 7; $i++) {
            $lastStatus = $this->post(route('contact.store'), $this->contactPayload([
                'email' => "flood{$i}@example.test",
            ]))->getStatusCode();

            if ($lastStatus === 429) {
                break;
            }
        }

        $this->assertSame(429, $lastStatus, 'The public write throttle never engaged.');
        $this->assertLessThanOrEqual(5, Lead::count());
    }

    #[DataProvider('throttledRoutes')]
    public function test_every_public_write_route_carries_the_throttle(string $name): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Missing route [{$name}].");
        $this->assertContains('throttle:5,1', $route->gatherMiddleware(), "[{$name}] is not throttled.");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function throttledRoutes(): array
    {
        return [
            'contact (ar)' => ['contact.store'],
            'contact (en)' => ['contact.store.en'],
            'comments (ar)' => ['articles.comments.store'],
            'comments (en)' => ['articles.comments.store.en'],
            'package enquiry (ar)' => ['packages.enquire'],
            'package enquiry (en)' => ['packages.enquire.en'],
        ];
    }

    // -----------------------------------------------------------------
    // Locale-correct redirects and flashes
    // -----------------------------------------------------------------

    public function test_an_english_submission_returns_to_the_english_page(): void
    {
        $response = $this->post(route('contact.store.en'), $this->contactPayload());

        // lroute(), not route(): an English visitor must not be dropped
        // onto the Arabic site mid-enquiry.
        $response->assertRedirect(route('contact.en').'#contact-form');
        $response->assertSessionHas('status', __('site.flash.contact_submitted', [], 'en'));
    }

    public function test_an_arabic_submission_returns_to_the_arabic_page(): void
    {
        $this->post(route('contact.store'), $this->contactPayload())
            ->assertRedirect(route('contact').'#contact-form')
            ->assertSessionHas('status', __('site.flash.contact_submitted', [], 'ar'));
    }

    public function test_the_flash_messages_exist_in_both_locales_and_differ(): void
    {
        foreach (['contact_submitted', 'comment_submitted', 'package_enquiry_submitted'] as $key) {
            $ar = __('site.flash.'.$key, [], 'ar');
            $en = __('site.flash.'.$key, [], 'en');

            // A missing key returns the key itself, which is how an
            // untranslated string reaches production unnoticed.
            $this->assertNotSame('site.flash.'.$key, $ar);
            $this->assertNotSame('site.flash.'.$key, $en);
            $this->assertNotSame($ar, $en);
        }
    }

    // -----------------------------------------------------------------
    // Comments
    // -----------------------------------------------------------------

    public function test_a_comment_is_created_pending_and_stays_off_the_site(): void
    {
        $article = Article::live()->firstOrFail();

        $this->post(route('articles.comments.store', $article), [
            'name' => 'Reader',
            'email' => 'reader@example.test',
            'body' => 'Great article',
        ])->assertRedirect(route('articles.show', $article).'#comments');

        $comment = Comment::latest('id')->firstOrFail();

        $this->assertSame(Comment::STATUS_PENDING, $comment->status);
        $this->get(route('articles.show', $article))->assertDontSee('Great article', false);
    }

    public function test_a_comment_cannot_arrive_pre_approved(): void
    {
        $article = Article::live()->firstOrFail();

        $this->post(route('articles.comments.store', $article), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.test',
            'body' => 'Approve me',
            'status' => Comment::STATUS_APPROVED,
        ]);

        $this->assertSame(Comment::STATUS_PENDING, Comment::latest('id')->firstOrFail()->status);
    }

    public function test_a_comment_body_is_escaped_when_it_is_finally_shown(): void
    {
        $article = Article::live()->firstOrFail();

        $this->post(route('articles.comments.store', $article), [
            'name' => 'Attacker',
            'email' => 'attacker@example.test',
            'body' => 'Hi <script>alert(1)</script>',
        ]);

        Comment::latest('id')->firstOrFail()->update(['status' => Comment::STATUS_APPROVED]);

        $content = $this->get(route('articles.show', $article))->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    public function test_commenting_on_an_unpublished_article_is_a_404(): void
    {
        foreach ([Article::factory()->draft()->create(), Article::factory()->scheduled()->create()] as $article) {
            // The form is never rendered for these, but a direct POST does
            // not go through the page that renders it.
            $this->post(route('articles.comments.store', $article), [
                'name' => 'Reader',
                'email' => 'reader@example.test',
                'body' => 'Hello',
            ])->assertNotFound();
        }

        $this->assertSame(0, Comment::count());
    }

    public function test_a_tripped_comment_honeypot_writes_nothing(): void
    {
        $article = Article::live()->firstOrFail();

        $this->post(route('articles.comments.store', $article), [
            'name' => 'Bot',
            'email' => 'bot@example.test',
            'body' => 'spam',
            Honeypot::FIELD => 'http://spam.test',
        ])->assertRedirect()->assertSessionHas('status', __('site.flash.comment_submitted'));

        $this->assertSame(0, Comment::count());
    }

    public function test_a_comment_creates_no_lead(): void
    {
        $article = Article::live()->firstOrFail();

        $this->post(route('articles.comments.store', $article), [
            'name' => 'Reader', 'email' => 'reader@example.test', 'body' => 'Nice',
        ]);

        // A comment is engagement, not a conversion — counting one as a
        // lead would inflate every conversion figure in the reports.
        $this->assertSame(0, Lead::count());
    }

    // -----------------------------------------------------------------
    // Package enquiry
    // -----------------------------------------------------------------

    public function test_a_package_enquiry_creates_a_lead_carrying_the_chosen_package(): void
    {
        $package = Package::active()->ordered()->firstOrFail();

        $this->post(route('packages.enquire'), [
            'package_id' => $package->id,
            'full_name' => 'خالد',
            'email' => 'khalid@example.test',
            'consent_given' => '1',
        ])
            ->assertRedirect(route('packages.index').'#packages')
            ->assertSessionHas('status', __('site.flash.package_enquiry_submitted'));

        $lead = Lead::latest('id')->firstOrFail();

        $this->assertSame(Lead::TYPE_PACKAGE_ENQUIRY, $lead->type);
        $this->assertSame($package->id, $lead->package_id);
        $this->assertTrue($lead->package->is($package));
    }

    public function test_an_enquiry_for_an_inactive_package_is_rejected(): void
    {
        $package = Package::factory()->inactive()->create();

        // Validated against what is actually on sale, so a crafted payload
        // cannot attach an enquiry to a withdrawn package.
        $this->post(route('packages.enquire'), [
            'package_id' => $package->id,
            'full_name' => 'Test',
            'email' => 'test@example.test',
            'consent_given' => '1',
        ])->assertSessionHasErrors('package_id');

        $this->assertSame(0, Lead::count());
    }

    public function test_a_retired_package_still_names_itself_on_its_past_enquiries(): void
    {
        $package = Package::active()->ordered()->firstOrFail();

        $this->post(route('packages.enquire'), [
            'package_id' => $package->id,
            'full_name' => 'Test', 'email' => 'test@example.test', 'consent_given' => '1',
        ]);

        $package->delete();

        // Package soft-deletes and Lead::package() resolves withTrashed(),
        // so the historical enquiry keeps saying what was chosen.
        $this->assertNotNull(Lead::latest('id')->firstOrFail()->package);
    }

    public function test_a_package_enquiry_carries_attribution_too(): void
    {
        $package = Package::active()->ordered()->firstOrFail();

        $this->post(route('packages.enquire'), [
            'package_id' => $package->id,
            'full_name' => 'Test', 'email' => 'test@example.test', 'consent_given' => '1',
            'latest_touch_snapshot' => json_encode(['utm_source' => 'GOOGLE']),
        ]);

        $this->assertSame('google', Lead::latest('id')->firstOrFail()->source_platform);
    }
}
