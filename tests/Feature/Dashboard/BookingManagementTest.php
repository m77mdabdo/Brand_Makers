<?php

namespace Tests\Feature\Dashboard;

use App\Models\Booking;
use App\Models\BookingAvailability;
use App\Models\BookingBlackoutDate;
use App\Models\Lead;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\Booking\BookingReportService;
use App\Services\Booking\BookingSlotService;
use App\Services\Cms\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The dashboard side: availability configuration, the booking queue, and
 * the status graph.
 */
class BookingManagementTest extends TestCase
{
    use RefreshDatabase;

    private const FROZEN = '2026-09-01 08:00:00';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FROZEN, 'Asia/Riyadh'));

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * A complete week payload — the form always submits all seven days,
     * so a test that submitted fewer would not be exercising the real
     * shape.
     *
     * @param  array<int, array<string, mixed>>  $overrides
     * @return array<string, mixed>
     */
    private function weekPayload(array $overrides = []): array
    {
        $days = [];

        for ($day = 0; $day <= 6; $day++) {
            $days[$day] = array_replace([
                'is_open' => '0',
                'opens_at' => '09:00',
                'closes_at' => '17:00',
                'slot_minutes' => '45',
                'capacity' => '1',
            ], $overrides[$day] ?? []);
        }

        return ['days' => $days];
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
            'bookings index' => ['dashboard.bookings.index', false],
            'agenda' => ['dashboard.bookings.agenda', false],
            'booking detail' => ['dashboard.bookings.show', true],
            'availability' => ['dashboard.booking-availability.edit', false],
        ];
    }

    #[DataProvider('guardedScreens')]
    public function test_the_screen_redirects_a_guest(string $route, bool $needsBooking): void
    {
        $url = $needsBooking ? route($route, Booking::factory()->create()) : route($route);

        $this->get($url)->assertRedirect(route('login'));
    }

    #[DataProvider('guardedScreens')]
    public function test_the_screen_is_forbidden_to_a_non_admin(string $route, bool $needsBooking): void
    {
        $url = $needsBooking ? route($route, Booking::factory()->create()) : route($route);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    #[DataProvider('guardedScreens')]
    public function test_an_admin_reaches_the_screen(string $route, bool $needsBooking): void
    {
        $url = $needsBooking ? route($route, Booking::factory()->create()) : route($route);

        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    public function test_a_non_admin_cannot_change_a_status_or_the_availability(): void
    {
        $booking = Booking::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('dashboard.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->put(route('dashboard.booking-availability.update'), $this->weekPayload())
            ->assertForbidden();

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_the_sidebar_links_to_both_sections(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertSee(route('dashboard.bookings.index'), false)
            ->assertSee(route('dashboard.booking-availability.edit'), false);
    }

    public function test_the_sidebar_badge_counts_pending_bookings_only(): void
    {
        Booking::factory()->count(3)->create();
        Booking::factory()->confirmed()->create();
        Booking::factory()->cancelled()->create();

        // Confirmed and completed need no decision; cancelled is closed.
        $this->assertSame(3, app(BookingReportService::class)->pendingCount());

        // Blade renders the count on its own line inside the badge, so
        // this matches the span rather than a bare ">3".
        $this->assertMatchesRegularExpression(
            '/min-w-\[1\.5rem\][^>]*>\s*3\s*</',
            $this->actingAs($this->admin)->get(route('dashboard'))->getContent(),
        );
    }

    public function test_the_badge_is_absent_when_nothing_is_pending(): void
    {
        Booking::factory()->confirmed()->create();

        // Rendered only when there IS something to decide, so the sidebar
        // is not permanently decorated with a zero.
        $this->assertSame(0, app(BookingReportService::class)->pendingCount());
        $this->assertDoesNotMatchRegularExpression(
            '/min-w-\[1\.5rem\][^>]*>\s*\d+\s*</',
            $this->actingAs($this->admin)->get(route('dashboard'))->getContent(),
        );
    }

    // -----------------------------------------------------------------
    // Availability configuration
    // -----------------------------------------------------------------

    public function test_an_admin_can_open_a_day(): void
    {
        $this->actingAs($this->admin)
            ->put(route('dashboard.booking-availability.update'), $this->weekPayload([
                BookingAvailability::WEDNESDAY => [
                    'is_open' => '1', 'opens_at' => '10:00', 'closes_at' => '14:00',
                    'slot_minutes' => '30', 'capacity' => '2',
                ],
            ]))
            ->assertRedirect(route('dashboard.booking-availability.edit'));

        $wednesday = BookingAvailability::where('day_of_week', BookingAvailability::WEDNESDAY)->sole();

        $this->assertTrue($wednesday->is_open);
        $this->assertSame('10:00:00', $wednesday->opens_at);
        $this->assertSame(30, $wednesday->slot_minutes);
        $this->assertSame(2, $wednesday->capacity);
    }

    public function test_opening_a_day_opens_it_on_the_public_page(): void
    {
        Setting::updateOrCreate(['key' => 'booking_minimum_notice_hours'], ['value' => 0, 'group' => 'booking']);

        $this->get('/book')->assertSee(__('site.booking.not_open'), false);

        $this->actingAs($this->admin)->put(route('dashboard.booking-availability.update'), $this->weekPayload([
            BookingAvailability::WEDNESDAY => ['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '12:00', 'slot_minutes' => '60', 'capacity' => '1'],
        ]));

        // Nothing about availability is hardcoded: what the studio sets
        // here is what a visitor can book.
        $this->get('/book')
            ->assertDontSee(__('site.booking.not_open'), false)
            ->assertSee('09:00', false);
    }

    public function test_a_closed_day_keeps_its_hours(): void
    {
        $this->actingAs($this->admin)->put(route('dashboard.booking-availability.update'), $this->weekPayload([
            BookingAvailability::FRIDAY => ['is_open' => '0', 'opens_at' => '11:00', 'closes_at' => '15:00', 'slot_minutes' => '60', 'capacity' => '4'],
        ]));

        $friday = BookingAvailability::where('day_of_week', BookingAvailability::FRIDAY)->sole();

        // An admin reopening Friday should find last month's hours
        // waiting rather than a blank form.
        $this->assertFalse($friday->is_open);
        $this->assertSame('11:00:00', $friday->opens_at);
        $this->assertSame(4, $friday->capacity);
    }

    public function test_unticking_a_day_closes_it(): void
    {
        BookingAvailability::updateOrCreate(['day_of_week' => BookingAvailability::SUNDAY], ['is_open' => true]);

        // A checkbox is absent from the payload when unticked, so
        // "not submitted" has to mean false here rather than "leave
        // alone" — otherwise a day could never be closed.
        $payload = $this->weekPayload();
        unset($payload['days'][BookingAvailability::SUNDAY]['is_open']);

        $this->actingAs($this->admin)->put(route('dashboard.booking-availability.update'), $payload);

        $this->assertFalse(BookingAvailability::where('day_of_week', BookingAvailability::SUNDAY)->sole()->is_open);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidDayConfigs(): array
    {
        return [
            'closing before opening' => [['is_open' => '1', 'opens_at' => '17:00', 'closes_at' => '09:00'], 'days.3.closes_at'],
            'a slot longer than the window' => [['is_open' => '1', 'opens_at' => '09:00', 'closes_at' => '10:00', 'slot_minutes' => '120'], 'days.3.slot_minutes'],
            'a slot of zero minutes' => [['slot_minutes' => '0'], 'days.3.slot_minutes'],
            'no capacity' => [['capacity' => '0'], 'days.3.capacity'],
            'a malformed time' => [['opens_at' => 'morning'], 'days.3.opens_at'],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    #[DataProvider('invalidDayConfigs')]
    public function test_an_invalid_day_is_rejected_and_names_the_day(array $config, string $field): void
    {
        // Without these checks the day would save happily and then
        // silently offer nothing — the hardest misconfiguration to
        // diagnose, because the public page just looks quiet.
        $this->actingAs($this->admin)
            ->put(route('dashboard.booking-availability.update'), $this->weekPayload([
                BookingAvailability::WEDNESDAY => $config,
            ]))
            ->assertSessionHasErrors($field);
    }

    public function test_a_closed_day_with_a_nonsense_window_is_not_rejected(): void
    {
        // The window only has to make sense on a day that is actually
        // open — otherwise an admin could not park odd hours on a closed
        // day while they work them out.
        $this->actingAs($this->admin)
            ->put(route('dashboard.booking-availability.update'), $this->weekPayload([
                BookingAvailability::FRIDAY => ['is_open' => '0', 'opens_at' => '17:00', 'closes_at' => '09:00'],
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_key_outside_the_week_is_never_written(): void
    {
        $payload = $this->weekPayload();
        $payload['days'][9] = ['is_open' => '1', 'opens_at' => '00:00', 'closes_at' => '23:59', 'slot_minutes' => '5', 'capacity' => '50'];
        $payload['days'][3]['id'] = 999;
        $payload['days'][3]['created_at'] = '1999-01-01 00:00:00';

        $this->actingAs($this->admin)->put(route('dashboard.booking-availability.update'), $payload);

        // The service iterates the seven weekdays and pulls values out by
        // index; the request has no rule for anything else.
        $this->assertSame(7, BookingAvailability::count());
        $this->assertNull(BookingAvailability::where('day_of_week', 9)->first());
        $this->assertTrue(BookingAvailability::where('day_of_week', 3)->sole()->created_at->isToday());
    }

    public function test_the_editor_warns_when_no_day_is_open(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard.booking-availability.edit'))
            ->assertSee(__('dashboard.booking_availability.not_open_warning'), false);
    }

    // -----------------------------------------------------------------
    // Blackout dates
    // -----------------------------------------------------------------

    public function test_an_admin_can_close_a_date(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.booking-availability.blackouts.store'), [
                'date' => '2026-09-02',
                'reason' => ['ar' => 'عطلة', 'en' => 'Holiday'],
            ])
            ->assertRedirect(route('dashboard.booking-availability.edit'));

        $blackout = BookingBlackoutDate::sole();

        $this->assertSame('2026-09-02', $blackout->date->toDateString());
        $this->assertSame('Holiday', $blackout->getTranslation('reason', 'en'));
    }

    public function test_closing_the_same_date_twice_updates_rather_than_erroring(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.booking-availability.blackouts.store'), [
            'date' => '2026-09-02', 'reason' => ['ar' => 'أول'],
        ]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.booking-availability.blackouts.store'), [
                'date' => '2026-09-02', 'reason' => ['ar' => 'ثانٍ'],
            ])
            ->assertSessionHasNoErrors();

        // `date` is unique; restating a closure with a better reason is
        // not a mistake worth a validation error.
        $this->assertSame(1, BookingBlackoutDate::count());
        $this->assertSame('ثانٍ', BookingBlackoutDate::sole()->getTranslation('reason', 'ar'));
    }

    public function test_markup_in_a_reason_is_stripped(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.booking-availability.blackouts.store'), [
            'date' => '2026-09-02', 'reason' => ['ar' => 'عطلة <script>alert(1)</script>'],
        ]);

        $this->assertStringNotContainsString('<script>', BookingBlackoutDate::sole()->getTranslation('reason', 'ar'));
    }

    public function test_removing_a_closure_reopens_the_day(): void
    {
        Setting::updateOrCreate(['key' => 'booking_minimum_notice_hours'], ['value' => 0, 'group' => 'booking']);
        BookingAvailability::updateOrCreate(['day_of_week' => BookingAvailability::WEDNESDAY], [
            'is_open' => true, 'opens_at' => '09:00:00', 'closes_at' => '12:00:00', 'slot_minutes' => 60, 'capacity' => 1,
        ]);

        $blackout = BookingBlackoutDate::create(['date' => '2026-09-02']);

        $this->get('/book')->assertDontSee('2026-09-02', false);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.booking-availability.blackouts.destroy', $blackout))
            ->assertRedirect();

        $this->assertSame(0, BookingBlackoutDate::count());
        $this->assertNotSame([], app(BookingSlotService::class)->slotsOn('2026-09-02'));
    }

    // -----------------------------------------------------------------
    // The booking queue
    // -----------------------------------------------------------------

    public function test_the_index_filters_by_status(): void
    {
        Booking::factory()->create(['name' => 'Pending Person']);
        Booking::factory()->confirmed()->create(['name' => 'Confirmed Person']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.bookings.index', ['status' => Booking::STATUS_CONFIRMED]))
            ->assertSee('Confirmed Person', false)
            ->assertDontSee('Pending Person', false);
    }

    public function test_the_index_filters_by_service(): void
    {
        $service = Service::factory()->create();

        Booking::factory()->create(['name' => 'With Topic', 'service_id' => $service->id]);
        Booking::factory()->create(['name' => 'No Topic', 'service_id' => null]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.bookings.index', ['service_id' => $service->id]))
            ->assertSee('With Topic', false)
            ->assertDontSee('No Topic', false);
    }

    public function test_the_index_filters_by_a_date_range_read_on_the_studios_clock(): void
    {
        // 01:00 Riyadh on the 2nd is 22:00 UTC on the 1st. A filter that
        // built its window in UTC would drop this from the 2nd's results
        // entirely.
        Booking::factory()->at(CarbonImmutable::parse('2026-09-02 01:00', 'Asia/Riyadh')->utc())->create(['name' => 'Early Riyadh']);
        Booking::factory()->at(CarbonImmutable::parse('2026-09-05 10:00', 'Asia/Riyadh')->utc())->create(['name' => 'Later Week']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.bookings.index', ['date_from' => '2026-09-02', 'date_to' => '2026-09-02']))
            ->assertSee('Early Riyadh', false)
            ->assertDontSee('Later Week', false);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function junkFilters(): array
    {
        return [
            'an unknown status' => [['status' => 'maybe']],
            'a non-numeric service' => [['service_id' => 'abc']],
            'a date that is not a date' => [['date_from' => 'tomorrow']],
            'an injection attempt' => [['date_to' => "2026-09-02' OR 1=1--"]],
        ];
    }

    /**
     * @param  array<string, string>  $filter
     */
    #[DataProvider('junkFilters')]
    public function test_a_junk_filter_is_dropped_rather_than_emptying_the_queue(array $filter): void
    {
        Booking::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('dashboard.bookings.index', $filter));

        $response->assertOk();
        $this->assertCount(3, $response->viewData('bookings'));
    }

    public function test_the_list_shows_the_local_time_not_the_stored_utc_one(): void
    {
        Booking::factory()->at(CarbonImmutable::parse('2026-09-02 10:00', 'Asia/Riyadh')->utc())->create();

        // Stored as 07:00 UTC; an admin must see 10:00.
        $this->actingAs($this->admin)
            ->get(route('dashboard.bookings.index'))
            ->assertSee('10:00', false)
            ->assertDontSee('07:00', false);
    }

    // -----------------------------------------------------------------
    // The agenda
    // -----------------------------------------------------------------

    public function test_the_agenda_shows_one_day_in_order(): void
    {
        Booking::factory()->at(CarbonImmutable::parse('2026-09-02 11:00', 'Asia/Riyadh')->utc())->create(['name' => 'Later Booking']);
        Booking::factory()->at(CarbonImmutable::parse('2026-09-02 09:00', 'Asia/Riyadh')->utc())->create(['name' => 'Earlier Booking']);
        Booking::factory()->at(CarbonImmutable::parse('2026-09-03 09:00', 'Asia/Riyadh')->utc())->create(['name' => 'Other Day']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.bookings.agenda', ['date' => '2026-09-02']))
            ->assertSeeInOrder(['Earlier Booking', 'Later Booking'], false)
            ->assertDontSee('Other Day', false);
    }

    public function test_the_agenda_shows_cancelled_bookings_rather_than_hiding_them(): void
    {
        Booking::factory()->cancelled()->at(CarbonImmutable::parse('2026-09-02 09:00', 'Asia/Riyadh')->utc())->create(['name' => 'Cancelled Person']);

        // An empty gap is something an admin might double-book into by
        // hand; a struck-through row is not.
        $this->actingAs($this->admin)
            ->get(route('dashboard.bookings.agenda', ['date' => '2026-09-02']))
            ->assertSee('Cancelled Person', false);
    }

    public function test_the_agenda_defaults_to_today_and_survives_a_junk_date(): void
    {
        foreach (['', 'nonsense', '2026-13-45'] as $date) {
            $response = $this->actingAs($this->admin)->get(route('dashboard.bookings.agenda', ['date' => $date]));

            $response->assertOk();
            $this->assertSame('2026-09-01', $response->viewData('date')->toDateString());
        }
    }

    // -----------------------------------------------------------------
    // Status transitions
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function transitions(): array
    {
        return [
            'pending to confirmed' => [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, true],
            'pending to cancelled' => [Booking::STATUS_PENDING, Booking::STATUS_CANCELLED, true],
            'pending to completed' => [Booking::STATUS_PENDING, Booking::STATUS_COMPLETED, false],
            'confirmed to completed' => [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED, true],
            'confirmed to cancelled' => [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED, true],
            'confirmed back to pending' => [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING, false],
            'completed to anything' => [Booking::STATUS_COMPLETED, Booking::STATUS_CONFIRMED, false],
            'cancelled back to confirmed' => [Booking::STATUS_CANCELLED, Booking::STATUS_CONFIRMED, false],
            'cancelled to completed' => [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_the_status_graph_is_enforced(string $from, string $to, bool $allowed): void
    {
        $booking = Booking::factory()->create();
        $booking->forceFill(['status' => $from])->save();

        $this->actingAs($this->admin)->patch(route('dashboard.bookings.status', $booking), ['status' => $to]);

        $this->assertSame($allowed ? $to : $from, $booking->fresh()->status);
    }

    public function test_a_refused_transition_says_so_rather_than_erroring(): void
    {
        $booking = Booking::factory()->completed()->create();

        // The only way to reach this is a stale screen — two admins on the
        // same booking, or a back button.
        $this->actingAs($this->admin)
            ->patch(route('dashboard.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])
            ->assertRedirect()
            ->assertSessionHas('error', __('dashboard.bookings.transition_refused'));
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $booking = Booking::factory()->create();

        $this->actingAs($this->admin)->patch(route('dashboard.bookings.status', $booking), ['status' => 'approved']);

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_status_cannot_be_mass_assigned(): void
    {
        $booking = Booking::create([
            'name' => 'Visitor', 'email' => 'v@example.test',
            'scheduled_at' => CarbonImmutable::parse('2026-09-02 09:00', 'Asia/Riyadh')->utc(),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // A booking that could confirm itself from a payload would let a
        // visitor skip the review step entirely.
        $this->assertSame(Booking::STATUS_PENDING, $booking->status);
    }

    public function test_cancelling_releases_the_slot_for_someone_else(): void
    {
        Setting::updateOrCreate(['key' => 'booking_minimum_notice_hours'], ['value' => 0, 'group' => 'booking']);
        BookingAvailability::updateOrCreate(['day_of_week' => BookingAvailability::WEDNESDAY], [
            'is_open' => true, 'opens_at' => '09:00:00', 'closes_at' => '12:00:00', 'slot_minutes' => 60, 'capacity' => 1,
        ]);

        $booking = Booking::factory()->at(CarbonImmutable::parse('2026-09-02 09:00', 'Asia/Riyadh')->utc())->create();

        $this->assertNotContains('09:00', array_column(app(BookingSlotService::class)->slotsOn('2026-09-02'), 'time'));

        $this->actingAs($this->admin)->patch(route('dashboard.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED]);

        $this->assertContains('09:00', array_column(app(BookingSlotService::class)->slotsOn('2026-09-02'), 'time'));
    }

    public function test_the_detail_screen_offers_only_the_moves_that_are_allowed(): void
    {
        $booking = Booking::factory()->confirmed()->create();

        $response = $this->actingAs($this->admin)->get(route('dashboard.bookings.show', $booking));

        $response->assertSee(__('dashboard.bookings.transition_to.completed'), false);
        $response->assertSee(__('dashboard.bookings.transition_to.cancelled'), false);
        // Never back to confirmed. Asserted on the submitted VALUE, not on
        // the button's label: the Arabic word for "confirm" is a substring
        // of the pending status label ("بانتظار التأكيد"), so a label
        // assertion would fail on an unrelated part of the page.
        $response->assertDontSee('value="'.Booking::STATUS_CONFIRMED.'"', false);
        $response->assertSee('value="'.Booking::STATUS_COMPLETED.'"', false);
    }

    public function test_a_terminal_booking_offers_no_moves_and_says_why(): void
    {
        $booking = Booking::factory()->cancelled()->create();

        $this->actingAs($this->admin)
            ->get(route('dashboard.bookings.show', $booking))
            ->assertSee(__('dashboard.bookings.terminal'), false);
    }

    public function test_the_detail_screen_links_to_the_lead(): void
    {
        $lead = Lead::factory()->create(['type' => Lead::TYPE_BOOKING]);
        $booking = Booking::factory()->create(['lead_id' => $lead->id]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.bookings.show', $booking))
            ->assertSee(route('dashboard.leads.show', $lead), false);
    }

    // -----------------------------------------------------------------
    // The booking window settings
    // -----------------------------------------------------------------

    public function test_both_booking_settings_are_editable(): void
    {
        $keys = SettingsService::keys();

        $this->assertSame(SettingsService::TYPE_INTEGER, $keys['booking_window_days'] ?? null);
        $this->assertSame(SettingsService::TYPE_INTEGER, $keys['booking_minimum_notice_hours'] ?? null);
    }

    public function test_a_notice_period_of_zero_is_accepted(): void
    {
        $payload = [];

        foreach (SettingsService::keys() as $key => $type) {
            $payload[$key] = match ($type) {
                SettingsService::TYPE_IMAGE => null,
                SettingsService::TYPE_TRANSLATED,
                SettingsService::TYPE_TRANSLATED_AREA => ['ar' => 'قيمة', 'en' => 'Value'],
                SettingsService::TYPE_EMAIL => 'hello@example.test',
                SettingsService::TYPE_PHONE => '+966500000000',
                SettingsService::TYPE_URL => 'https://example.test/page',
                SettingsService::TYPE_INTEGER => 30,
                SettingsService::TYPE_BOOLEAN => '1',
                default => 'value',
            };
        }

        $payload['booking_minimum_notice_hours'] = 0;

        // A studio happy to take a booking for an hour's time is a real
        // policy, and the slot generator already clamps at 0 — the editor
        // must not refuse a value the rest of the system supports.
        $this->actingAs($this->admin)
            ->put(route('dashboard.settings.update'), ['settings' => $payload])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Setting::where('key', 'booking_minimum_notice_hours')->sole()->value);
    }
}
