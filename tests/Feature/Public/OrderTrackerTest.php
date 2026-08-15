<?php

namespace Tests\Feature\Public;

use App\Models\Order;
use App\Models\Package;
use App\Services\Orders\OrderLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The client portal, and the things it must refuse to tell anybody.
 */
class OrderTrackerTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private string $code;

    protected function setUp(): void
    {
        parent::setUp();

        // The plaintext is readable exactly once, from the object that
        // issued it — which is why the tests have to capture it here.
        $this->order = Order::factory()->create(['client_name' => 'Noura Client']);
        $this->code = $this->order->trackingCode();

        RateLimiter::clear('order-tracking');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function lookup(array $overrides = []): TestResponse
    {
        return $this->post(route('orders.track.lookup'), array_replace([
            'reference' => $this->order->reference,
            'tracking_code' => $this->code,
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // The happy path
    // -----------------------------------------------------------------

    public function test_the_page_renders_the_lookup_form_in_both_locales(): void
    {
        foreach (['/track' => 'ar', '/en/track' => 'en'] as $url => $locale) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee(__('site.tracker.title', [], $locale), false);
            $response->assertSee(__('site.tracker.reference', [], $locale), false);
        }
    }

    public function test_a_correct_pair_opens_the_order(): void
    {
        $this->lookup()->assertRedirect(lroute('orders.track').'#order-status');

        $response = $this->get('/track');

        $response->assertOk();
        $response->assertSee($this->order->reference, false);
        $response->assertSee(__('site.tracker.stages_heading'), false);
    }

    public function test_the_reference_is_matched_case_insensitively_and_ignoring_spaces(): void
    {
        // A client reading "CM-2026-7QK4TP" off a phone call types it with
        // spaces where the dashes are, and in whatever case they like.
        $spaced = strtolower(str_replace('-', ' ', $this->order->reference));

        $this->lookup(['reference' => $spaced])->assertRedirect(lroute('orders.track').'#order-status');
    }

    public function test_the_order_shows_its_package_progress_and_stages(): void
    {
        $package = Package::factory()->create(['name' => ['ar' => 'الأساسية', 'en' => 'Starter']]);
        $this->order->update(['package_id' => $package->id]);
        $this->order->transitionTo(Order::STATUS_IN_PROGRESS, ['ar' => 'بدأنا العمل'], 40);

        $this->lookup();

        $response = $this->get('/track');

        $response->assertSee('40%', false);
        $response->assertSee(__('site.tracker.stages.in_progress'), false);
        $response->assertSee('بدأنا العمل', false);
        // All four stages are always shown, so the client can see what is
        // still ahead rather than only what has happened.
        $response->assertSee(__('site.tracker.stages.completed'), false);
    }

    // -----------------------------------------------------------------
    // Refusals — all identical
    // -----------------------------------------------------------------

    /**
     * The three ways a pair can be wrong.
     *
     * NOTE what is not here: "the other order's reference AND the other
     * order's code". That is not a wrong pair — it is a correct pair for a
     * different order, and it opens it, which is the whole feature.
     *
     * @return array<string, array{string, string}>
     */
    public static function wrongCredentials(): array
    {
        return [
            'a real reference with somebody else\'s code' => ['own', 'other'],
            'somebody else\'s reference with a real code' => ['other', 'own'],
            'a reference that does not exist, with a random code' => ['unknown', 'random'],
        ];
    }

    #[DataProvider('wrongCredentials')]
    public function test_every_wrong_combination_gives_the_same_generic_failure(string $referenceKind, string $codeKind): void
    {
        $other = Order::factory()->create(['client_name' => 'Someone Else']);

        $response = $this->lookup([
            'reference' => match ($referenceKind) {
                'own' => $this->order->reference,
                'other' => $other->reference,
                default => 'CM-2026-ZZZZZZ',
            },
            'tracking_code' => match ($codeKind) {
                'own' => $this->code,
                'other' => $other->trackingCode(),
                default => Order::generateTrackingCode(),
            },
        ]);

        // ONE message for every failure. Anything more specific would turn
        // the portal into an oracle for which references exist.
        $response->assertRedirect(lroute('orders.track').'#track-form');
        $response->assertSessionHas('error', __('site.tracker.not_found'));

        // No order is opened. Asserted on the client NAME rather than the
        // reference: a failed lookup deliberately echoes the reference
        // back into the form so the client does not retype it, so the
        // string is legitimately on the page.
        $this->get('/track')
            ->assertDontSee('Noura Client', false)
            ->assertDontSee('Someone Else', false)
            ->assertSee(__('site.tracker.form_heading'), false);
    }

    public function test_a_failed_lookup_returns_the_reference_but_never_the_code(): void
    {
        $response = $this->lookup(['tracking_code' => Order::generateTrackingCode()]);

        // The reference is not a credential and retyping it is a nuisance;
        // the code IS one and must not sit in a repopulated form.
        $response->assertSessionHasInput('reference', $this->order->reference);
        $this->assertArrayNotHasKey('tracking_code', session()->getOldInput());
    }

    public function test_a_reference_that_does_not_exist_looks_exactly_like_one_that_does(): void
    {
        $real = $this->lookup(['tracking_code' => Order::generateTrackingCode()]);
        $fake = $this->lookup([
            'reference' => 'CM-2026-ZZZZZZ',
            'tracking_code' => Order::generateTrackingCode(),
        ]);

        // Same status, same destination, same message — so the portal
        // cannot be used to discover which references exist.
        $this->assertSame($real->getStatusCode(), $fake->getStatusCode());
        $this->assertSame($real->headers->get('Location'), $fake->headers->get('Location'));
        $this->assertSame(session()->get('error'), __('site.tracker.not_found'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedCodes(): array
    {
        return [
            'too short' => ['abc123'],
            'not hex' => [str_repeat('z', 64)],
            'an injection attempt' => ["' OR 1=1--"],
            'a hash rather than a code' => [hash('sha256', 'anything')],
        ];
    }

    #[DataProvider('malformedCodes')]
    public function test_a_malformed_code_fails_the_same_way_as_a_wrong_one(string $code): void
    {
        // Validation deliberately does NOT reject these: a shape rule
        // would give a different, distinguishable response from a
        // well-formed wrong code.
        //
        // A MISSING field is different and is left as an ordinary
        // validation error — "you left the code blank" is a fact about the
        // form in front of the visitor, and reveals nothing about which
        // orders exist.
        $this->lookup(['tracking_code' => $code])
            ->assertRedirect(lroute('orders.track').'#track-form')
            ->assertSessionHas('error', __('site.tracker.not_found'));
    }

    public function test_a_blank_code_is_an_ordinary_validation_error(): void
    {
        // Not the generic failure: this one is about the form, not about
        // whether an order exists.
        $this->lookup(['tracking_code' => '   '])->assertSessionHasErrors('tracking_code');
    }

    public function test_presenting_the_stored_hash_does_not_open_the_order(): void
    {
        // The digest is what an attacker would get from a database dump.
        // It is not the code, and hashing it again does not match.
        $hash = Order::hashTrackingCode($this->code);

        $this->lookup(['tracking_code' => $hash])->assertSessionHas('error', __('site.tracker.not_found'));
    }

    public function test_an_archived_order_is_not_reachable(): void
    {
        $this->order->delete();

        $this->lookup()->assertSessionHas('error', __('site.tracker.not_found'));
    }

    // -----------------------------------------------------------------
    // Isolation
    // -----------------------------------------------------------------

    public function test_a_client_sees_only_their_own_order(): void
    {
        $other = Order::factory()->create(['client_name' => 'Someone Else']);

        $this->lookup();

        $response = $this->get('/track');

        $response->assertSee($this->order->reference, false);
        $response->assertDontSee($other->reference, false);
        $response->assertDontSee('Someone Else', false);
    }

    public function test_there_is_no_order_identifier_in_any_url_to_edit(): void
    {
        $response = $this->lookup();

        // The whole anti-tampering argument: not "the id is checked" but
        // "there is no id". The verified order lives in the session.
        $location = $response->headers->get('Location');

        $this->assertStringNotContainsString($this->order->reference, $location);
        $this->assertStringNotContainsString((string) $this->order->id, parse_url($location, PHP_URL_QUERY) ?? '');
        $this->assertSame(lroute('orders.track').'#order-status', $location);
    }

    public function test_a_second_lookup_replaces_the_first(): void
    {
        $second = Order::factory()->create();

        $this->lookup();
        $this->lookup(['reference' => $second->reference, 'tracking_code' => $second->trackingCode()]);

        $response = $this->get('/track');

        $response->assertSee($second->reference, false);
        $response->assertDontSee($this->order->reference, false);
    }

    public function test_forgetting_the_order_returns_to_the_form(): void
    {
        $this->lookup();
        $this->get('/track')->assertSee($this->order->reference, false);

        $this->post(route('orders.track.forget'))->assertRedirect(lroute('orders.track'));

        $this->get('/track')
            ->assertDontSee($this->order->reference, false)
            ->assertSee(__('site.tracker.form_heading'), false);
    }

    public function test_a_visitor_with_no_session_sees_only_the_form(): void
    {
        $this->get('/track')
            ->assertOk()
            ->assertSee(__('site.tracker.form_heading'), false)
            ->assertDontSee($this->order->reference, false);
    }

    public function test_an_order_archived_after_a_lookup_disappears_from_the_portal(): void
    {
        $this->lookup();
        $this->get('/track')->assertSee($this->order->reference, false);

        $this->order->delete();

        // Re-read on every request rather than cached in the session, so a
        // stale copy cannot linger.
        $this->get('/track')->assertDontSee($this->order->reference, false);
    }

    // -----------------------------------------------------------------
    // Rate limiting
    // -----------------------------------------------------------------

    public function test_the_lookup_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->lookup(['tracking_code' => Order::generateTrackingCode()])->assertRedirect();
        }

        // Not what makes the portal safe — the code carries 256 bits — but
        // it caps probing references against a code somebody already
        // holds.
        $this->lookup(['tracking_code' => Order::generateTrackingCode()])->assertStatus(429);
    }

    public function test_the_limit_does_not_apply_to_reading_the_page(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->get('/track')->assertOk();
        }
    }

    // -----------------------------------------------------------------
    // Escaping
    // -----------------------------------------------------------------

    public function test_timeline_notes_are_escaped(): void
    {
        $this->order->transitionTo(Order::STATUS_IN_PROGRESS, ['ar' => 'ملاحظة <script>alert(1)</script>']);

        $this->lookup();

        $content = $this->get('/track')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    public function test_the_order_note_is_escaped(): void
    {
        $this->order->update(['notes' => 'Note <img src=x onerror=alert(1)>']);

        $this->lookup();

        $content = $this->get('/track')->getContent();

        $this->assertStringNotContainsString('<img src=x', $content);
        $this->assertStringContainsString('&lt;img', $content);
    }

    // -----------------------------------------------------------------
    // The service, directly
    // -----------------------------------------------------------------

    public function test_the_lookup_service_never_matches_on_the_reference_alone(): void
    {
        $lookup = app(OrderLookupService::class);

        $this->assertNull($lookup->find($this->order->reference, ''));
        $this->assertNull($lookup->find($this->order->reference, null));
        $this->assertNotNull($lookup->find($this->order->reference, $this->code));
    }

    public function test_a_cancelled_order_still_opens_and_shows_how_far_it_got(): void
    {
        $this->order->transitionTo(Order::STATUS_IN_PROGRESS, null, 50);
        $this->order->transitionTo(Order::STATUS_CANCELLED);

        $this->lookup();

        // Cancelling is not a reason to stop a client seeing their own
        // order — and the stages show where it stopped.
        $this->get('/track')
            ->assertOk()
            ->assertSee(__('site.tracker.cancelled_note'), false)
            ->assertSee($this->order->reference, false);
    }
}
