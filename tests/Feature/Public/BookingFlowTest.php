<?php

namespace Tests\Feature\Public;

use App\Models\Booking;
use App\Models\BookingAvailability;
use App\Models\BookingBlackoutDate;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Service;
use App\Models\Setting;
use App\Support\Honeypot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public booking flow, end to end.
 */
class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    /** A Tuesday, 08:00 Riyadh. */
    private const FROZEN = '2026-09-01 08:00:00';

    /** The Wednesday the tests book into. */
    private const DATE = '2026-09-02';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FROZEN, 'Asia/Riyadh'));

        BookingAvailability::updateOrCreate(['day_of_week' => BookingAvailability::WEDNESDAY], [
            'is_open' => true,
            'opens_at' => '09:00:00',
            'closes_at' => '12:00:00',
            'slot_minutes' => 60,
            'capacity' => 1,
        ]);

        Setting::updateOrCreate(['key' => 'booking_minimum_notice_hours'], ['value' => 0, 'group' => 'booking']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'نورة',
            'email' => 'noura@example.test',
            'date' => self::DATE,
            'time' => '10:00',
            'consent_given' => '1',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // The page
    // -----------------------------------------------------------------

    public function test_the_page_renders_availability_in_both_locales(): void
    {
        foreach (['/book' => 'ar', '/en/book' => 'en'] as $url => $locale) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee(__('site.booking.title', [], $locale), false);
            $response->assertSee('data-booking-availability', false);
            // The times are in the payload the picker reads.
            $response->assertSee('09:00', false);
        }
    }

    public function test_the_page_states_the_timezone(): void
    {
        // A visitor in another country must not have to guess whose 10:00
        // this is.
        $this->get('/book')->assertSee(__('site.booking.timezone_label'), false);
    }

    public function test_the_page_says_booking_is_not_open_when_nothing_is_configured(): void
    {
        BookingAvailability::query()->update(['is_open' => false]);

        $response = $this->get('/book');

        $response->assertOk();
        $response->assertSee(__('site.booking.not_open'), false);
        $response->assertDontSee('data-booking-availability', false);
    }

    public function test_the_page_distinguishes_fully_booked_from_not_configured(): void
    {
        // Every day in the window blacked out: availability IS configured,
        // there is simply nothing left. Saying "not open yet" here would
        // be a different and wrong claim.
        for ($i = 0; $i <= 30; $i++) {
            BookingBlackoutDate::create(['date' => CarbonImmutable::parse(self::FROZEN, 'Asia/Riyadh')->addDays($i)->toDateString()]);
        }

        $this->get('/book')
            ->assertSee(__('site.booking.none_available'), false)
            ->assertDontSee(__('site.booking.not_open'), false);
    }

    public function test_the_navbar_gains_the_booking_entry_once_the_route_exists(): void
    {
        $this->seed();

        // The menu item ships seeded but dormant; registering the route
        // named 'booking' is what lights it up.
        $this->get('/')->assertSee(route('booking'), false);
    }

    // -----------------------------------------------------------------
    // A successful booking
    // -----------------------------------------------------------------

    public function test_a_submission_creates_a_pending_booking_and_a_lead(): void
    {
        $service = Service::factory()->create(['is_active' => true]);

        $this->post(route('booking.store'), $this->payload(['service_id' => $service->id, 'notes' => 'ملاحظة']))
            ->assertRedirect(lroute('booking').'#booking-confirmed')
            ->assertSessionHas('status', __('site.flash.booking_submitted'));

        $booking = Booking::sole();

        $this->assertSame(Booking::STATUS_PENDING, $booking->status);
        $this->assertSame('نورة', $booking->name);
        $this->assertSame($service->id, $booking->service_id);
        // 10:00 Riyadh is 07:00 UTC.
        $this->assertSame('2026-09-02 07:00:00', $booking->scheduled_at->utc()->toDateTimeString());
        $this->assertSame('10:00', $booking->scheduledAtLocal()->format('H:i'));

        $lead = Lead::sole();

        $this->assertSame(Lead::TYPE_BOOKING, $lead->type);
        $this->assertSame($lead->id, $booking->lead_id);
        $this->assertSame($service->id, $lead->requested_service_id);
        $this->assertTrue($lead->consent_given);
    }

    public function test_the_confirmation_shows_the_reference_and_the_appointment(): void
    {
        $this->post(route('booking.store'), $this->payload());

        $booking = Booking::sole();

        $response = $this->followingRedirects()->post(route('booking.store'), $this->payload(['time' => '11:00']));

        $response->assertOk();
        $response->assertSee(__('site.booking.reference'), false);
        $response->assertSee(Booking::latest('id')->firstOrFail()->reference, false);
        $this->assertMatchesRegularExpression('/^CM-\d{4}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/', $booking->reference);
    }

    public function test_the_reference_is_not_put_in_the_url(): void
    {
        $response = $this->post(route('booking.store'), $this->payload());

        // Flashed, not queried: a reference in the URL stays in history
        // and in the next referrer header, and would let anyone build a
        // confirmation screen for a reference they guessed.
        $this->assertStringNotContainsString(Booking::sole()->reference, $response->headers->get('Location'));
    }

    public function test_a_booking_without_a_topic_is_accepted(): void
    {
        $this->post(route('booking.store'), $this->payload(['service_id' => null]))->assertRedirect();

        // "Not sure yet" is a real answer; forcing a choice costs
        // bookings.
        $this->assertNull(Booking::sole()->service_id);
    }

    public function test_the_slot_disappears_from_the_page_once_taken(): void
    {
        $this->get('/book')->assertSee('10:00', false);

        $this->post(route('booking.store'), $this->payload());

        $availability = $this->get('/book')->viewData('days');
        $wednesday = $availability->firstWhere(fn (array $day) => $day['date']->toDateString() === self::DATE);

        $this->assertNotContains('10:00', array_column($wednesday['slots'], 'time'));
    }

    // -----------------------------------------------------------------
    // Slots that are not on offer
    // -----------------------------------------------------------------

    public function test_a_time_the_generator_never_offered_is_rejected(): void
    {
        $this->post(route('booking.store'), $this->payload(['time' => '09:17']))
            ->assertRedirect(lroute('booking').'#booking-form')
            ->assertSessionHas('error', __('site.booking.slot_gone'));

        $this->assertSame(0, Booking::count());
        // And no lead either: the transaction never started.
        $this->assertSame(0, Lead::count());
    }

    public function test_a_blacked_out_date_cannot_be_booked_even_with_a_valid_time(): void
    {
        BookingBlackoutDate::create(['date' => self::DATE]);

        $this->post(route('booking.store'), $this->payload())
            ->assertSessionHas('error', __('site.booking.slot_gone'));

        $this->assertSame(0, Booking::count());
    }

    public function test_a_second_booking_for_a_full_slot_is_refused(): void
    {
        $this->post(route('booking.store'), $this->payload())->assertRedirect();

        // Capacity is 1, and the slot is gone from the generated list by
        // the time this arrives — so resolveSlot() catches it before the
        // transaction and the visitor gets the "no longer offered"
        // wording. The genuine simultaneous race, where both requests get
        // past that point, is BookingRaceTest.
        $this->post(route('booking.store'), $this->payload(['email' => 'second@example.test']))
            ->assertSessionHas('error', __('site.booking.slot_gone'));

        $this->assertSame(1, Booking::count());
        $this->assertSame(1, Lead::count());
    }

    public function test_a_rejected_submission_keeps_the_visitors_details(): void
    {
        $this->post(route('booking.store'), $this->payload(['time' => '09:17', 'name' => 'محمد']))
            ->assertSessionHasInput('name', 'محمد');
    }

    // -----------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------

    public function test_the_name_email_slot_and_consent_are_required(): void
    {
        $this->post(route('booking.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'date', 'time', 'consent_given']);

        $this->assertSame(0, Booking::count());
    }

    public function test_an_inactive_service_cannot_be_booked(): void
    {
        $service = Service::factory()->create(['is_active' => false]);

        // A withdrawn topic must not be bookable from a stale page.
        $this->post(route('booking.store'), $this->payload(['service_id' => $service->id]))
            ->assertSessionHasErrors('service_id');
    }

    // -----------------------------------------------------------------
    // Honeypot, throttle, attribution, locale
    // -----------------------------------------------------------------

    public function test_the_honeypot_produces_an_identical_response_and_writes_nothing(): void
    {
        $real = $this->post(route('booking.store'), $this->payload());
        Booking::query()->forceDelete();
        Lead::query()->forceDelete();

        $trapped = $this->post(route('booking.store'), $this->payload([Honeypot::FIELD => 'https://spam.example']));

        $this->assertSame($real->getStatusCode(), $trapped->getStatusCode());
        $this->assertSame($real->headers->get('Location'), $trapped->headers->get('Location'));
        $trapped->assertSessionHas('status', __('site.flash.booking_submitted'));

        $this->assertSame(0, Booking::count());
        $this->assertSame(0, Lead::count());
    }

    public function test_submissions_are_throttled(): void
    {
        // Capacity 1 per slot and three slots on the day, so the first
        // three succeed on their own merits; the throttle is what stops
        // the sixth.
        BookingAvailability::where('day_of_week', BookingAvailability::WEDNESDAY)->update(['capacity' => 10]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('booking.store'), $this->payload(['email' => "v{$i}@example.test"]))->assertRedirect();
        }

        $this->post(route('booking.store'), $this->payload(['email' => 'sixth@example.test']))->assertStatus(429);

        $this->assertSame(5, Booking::count());
    }

    public function test_attribution_is_resolved_onto_the_lead(): void
    {
        LeadSource::factory()->create(['key' => 'instagram']);
        $campaign = Campaign::factory()->create(['external_campaign_id' => 'camp-booking']);

        $this->post(route('booking.store'), $this->payload([
            'latest_touch_snapshot' => json_encode([
                'utm_source' => 'INSTAGRAM',
                'campaign_id' => 'camp-booking',
            ]),
            'first_touch_snapshot' => json_encode(['utm_source' => 'google']),
        ]));

        $lead = Lead::sole();

        $this->assertSame('instagram', $lead->source_platform);
        $this->assertSame($campaign->id, $lead->linked_campaign_id);
        $this->assertSame('google', $lead->first_touch['utm_source']);
    }

    public function test_an_english_submission_redirects_back_to_the_english_page(): void
    {
        $this->post(route('booking.store.en'), $this->payload())
            ->assertRedirect(route('booking.en').'#booking-confirmed');
    }

    // -----------------------------------------------------------------
    // The notification seam
    // -----------------------------------------------------------------

    public function test_nothing_claims_a_message_was_sent(): void
    {
        $this->followingRedirects()->post(route('booking.store'), $this->payload())
            ->assertSee(__('site.booking.confirmed_next', ['timezone' => __('site.booking.timezone_label')]), false);

        // No transport is configured, so the confirmation says the studio
        // will be in touch — not that an email is on its way. See
        // BookingNotifier for the seam.
        $this->assertFalse(config('booking.notifications_enabled'));
    }
}
