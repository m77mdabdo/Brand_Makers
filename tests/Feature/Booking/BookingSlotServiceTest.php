<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingAvailability;
use App\Models\BookingBlackoutDate;
use App\Models\Setting;
use App\Services\Booking\BookingSlotService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What is bookable, and when.
 *
 * TIME IS FROZEN in setUp. Slot generation is entirely a function of "now"
 * — the notice period, the window, which day is today — so a test that let
 * the clock run would pass or fail depending on when it was run, and would
 * be unfixable at 23:59.
 *
 * The frozen instant is deliberately mid-morning RIYADH time, not UTC:
 * 08:00 in Riyadh is 05:00 UTC, so any test that accidentally reasoned in
 * UTC would produce a different set of slots and say so.
 */
class BookingSlotServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingSlotService $slots;

    /** A Tuesday, chosen so "tomorrow" is a plain midweek day. */
    private const FROZEN = '2026-09-01 08:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FROZEN, 'Asia/Riyadh'));

        $this->slots = app(BookingSlotService::class);
    }

    /**
     * One open weekday, everything else closed — so each test states
     * exactly the availability it depends on.
     */
    private function openOn(int $dayOfWeek, array $overrides = []): BookingAvailability
    {
        return BookingAvailability::updateOrCreate(
            ['day_of_week' => $dayOfWeek],
            array_replace([
                'is_open' => true,
                'opens_at' => '09:00:00',
                'closes_at' => '12:00:00',
                'slot_minutes' => 60,
                'capacity' => 1,
            ], $overrides),
        );
    }

    private function noNotice(): void
    {
        Setting::updateOrCreate(['key' => 'booking_minimum_notice_hours'], ['value' => 0, 'group' => 'booking']);
    }

    private function localDate(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'Asia/Riyadh')->startOfDay();
    }

    // -----------------------------------------------------------------
    // Configuration
    // -----------------------------------------------------------------

    public function test_nothing_is_bookable_when_no_day_is_open(): void
    {
        BookingAvailability::query()->update(['is_open' => false]);

        $this->assertFalse($this->slots->isConfigured());
        $this->assertTrue($this->slots->availableDays()->isEmpty());
    }

    public function test_a_configured_day_produces_slots(): void
    {
        $this->noNotice();
        // 2026-09-02 is a Wednesday.
        $this->openOn(BookingAvailability::WEDNESDAY);

        $this->assertTrue($this->slots->isConfigured());
        $this->assertSame(['09:00', '10:00', '11:00'], array_column($this->slots->slotsOn('2026-09-02'), 'time'));
    }

    public function test_a_slot_that_would_overrun_closing_time_is_not_offered(): void
    {
        $this->noNotice();
        // 09:00–12:00 in 45-minute slots: 09:00, 09:45, 10:30, 11:15 — and
        // NOT 12:00, which would end at 12:45.
        $this->openOn(BookingAvailability::WEDNESDAY, ['slot_minutes' => 45]);

        $this->assertSame(['09:00', '09:45', '10:30', '11:15'], array_column($this->slots->slotsOn('2026-09-02'), 'time'));
    }

    public function test_a_closed_weekday_produces_nothing(): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY);
        $this->openOn(BookingAvailability::THURSDAY, ['is_open' => false]);

        // 2026-09-03 is the Thursday.
        $this->assertSame([], $this->slots->slotsOn('2026-09-03'));
    }

    // -----------------------------------------------------------------
    // Blackout dates
    // -----------------------------------------------------------------

    public function test_a_blackout_date_removes_the_whole_day(): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY);

        $this->assertNotSame([], $this->slots->slotsOn('2026-09-02'));

        BookingBlackoutDate::create(['date' => '2026-09-02', 'reason' => ['ar' => 'عطلة', 'en' => 'Holiday']]);

        $this->assertSame([], $this->slots->slotsOn('2026-09-02'));
    }

    public function test_a_blacked_out_day_is_absent_from_the_calendar_entirely(): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY);
        BookingBlackoutDate::create(['date' => '2026-09-02']);

        $dates = $this->slots->availableDays()->map(fn (array $day) => $day['date']->toDateString());

        // Absent, not rendered empty: a visitor scanning for a date should
        // not have to open one to discover it is closed.
        $this->assertNotContains('2026-09-02', $dates->all());
    }

    public function test_a_blackout_on_another_date_does_not_affect_this_one(): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY);
        BookingBlackoutDate::create(['date' => '2026-09-09']);

        $this->assertNotSame([], $this->slots->slotsOn('2026-09-02'));
    }

    // -----------------------------------------------------------------
    // Capacity
    // -----------------------------------------------------------------

    public function test_a_slot_at_capacity_is_withheld(): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY, ['capacity' => 1]);

        Booking::factory()->at($this->localDate('2026-09-02')->setTime(10, 0)->utc())->create();

        $this->assertSame(['09:00', '11:00'], array_column($this->slots->slotsOn('2026-09-02'), 'time'));
    }

    public function test_a_slot_below_capacity_is_still_offered_and_reports_what_is_left(): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY, ['capacity' => 3]);

        Booking::factory()->at($this->localDate('2026-09-02')->setTime(10, 0)->utc())->create();

        $ten = collect($this->slots->slotsOn('2026-09-02'))->firstWhere('time', '10:00');

        $this->assertSame(2, $ten['remaining']);
    }

    public function test_a_cancelled_booking_releases_its_slot_immediately(): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY, ['capacity' => 1]);

        $at = $this->localDate('2026-09-02')->setTime(10, 0)->utc();

        Booking::factory()->cancelled()->at($at)->create();

        // Otherwise one abandoned form fills the calendar permanently.
        $this->assertContains('10:00', array_column($this->slots->slotsOn('2026-09-02'), 'time'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function slotHoldingStatuses(): array
    {
        return [
            'pending' => [Booking::STATUS_PENDING],
            'confirmed' => [Booking::STATUS_CONFIRMED],
            'completed' => [Booking::STATUS_COMPLETED],
        ];
    }

    #[DataProvider('slotHoldingStatuses')]
    public function test_every_live_status_holds_its_slot(string $status): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY, ['capacity' => 1]);

        $at = $this->localDate('2026-09-02')->setTime(10, 0)->utc();

        $booking = Booking::factory()->at($at)->create();
        $booking->forceFill(['status' => $status])->save();

        // A pending booking is still an appointment somebody expects to
        // keep, so it holds the slot exactly like a confirmed one.
        $this->assertNotContains('10:00', array_column($this->slots->slotsOn('2026-09-02'), 'time'));
    }

    public function test_remaining_at_counts_only_live_bookings(): void
    {
        $this->openOn(BookingAvailability::WEDNESDAY, ['capacity' => 2]);

        $at = $this->localDate('2026-09-02')->setTime(10, 0)->utc();

        Booking::factory()->at($at)->create();
        Booking::factory()->cancelled()->at($at)->create();

        $this->assertSame(1, $this->slots->remainingAt($at));
        $this->assertTrue($this->slots->hasCapacity($at));
    }

    // -----------------------------------------------------------------
    // Notice period and window
    // -----------------------------------------------------------------

    public function test_slots_inside_the_notice_period_are_not_offered(): void
    {
        Setting::updateOrCreate(['key' => 'booking_minimum_notice_hours'], ['value' => 24, 'group' => 'booking']);
        // Today is Tuesday 08:00 Riyadh, so 24 hours' notice rules out
        // everything before Wednesday 08:00.
        $this->openOn(BookingAvailability::WEDNESDAY, ['opens_at' => '07:00:00', 'closes_at' => '11:00:00']);

        $times = array_column($this->slots->slotsOn('2026-09-02'), 'time');

        $this->assertNotContains('07:00', $times);
        $this->assertContains('09:00', $times);
    }

    public function test_zero_notice_is_honoured_rather_than_treated_as_unset(): void
    {
        $this->noNotice();
        // Today, from 09:00 — an hour after the frozen 08:00.
        $this->openOn(BookingAvailability::TUESDAY, ['opens_at' => '09:00:00', 'closes_at' => '11:00:00']);

        // A studio happy to take a booking for an hour's time is a
        // legitimate configuration, so 0 clamps to 0, not to 1.
        $this->assertSame(0, $this->slots->minimumNoticeHours());
        $this->assertContains('09:00', array_column($this->slots->slotsOn('2026-09-01'), 'time'));
    }

    public function test_a_slot_earlier_today_is_never_offered(): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::TUESDAY, ['opens_at' => '06:00:00', 'closes_at' => '11:00:00']);

        $times = array_column($this->slots->slotsOn('2026-09-01'), 'time');

        $this->assertNotContains('06:00', $times);
        $this->assertNotContains('07:00', $times);
        $this->assertContains('09:00', $times);
    }

    public function test_a_date_past_the_window_produces_nothing(): void
    {
        $this->noNotice();
        Setting::updateOrCreate(['key' => 'booking_window_days'], ['value' => 7, 'group' => 'booking']);
        $this->openOn(BookingAvailability::WEDNESDAY);

        $this->assertNotSame([], $this->slots->slotsOn('2026-09-02'));
        // 2026-09-16 is a Wednesday well past a seven-day window.
        $this->assertSame([], $this->slots->slotsOn('2026-09-16'));
    }

    public function test_a_date_in_the_past_produces_nothing(): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY);

        $this->assertSame([], $this->slots->slotsOn('2026-08-26'));
    }

    /**
     * @return array<string, array{mixed, int}>
     */
    public static function windowSettings(): array
    {
        return [
            'the seeded default' => [30, 30],
            'a custom window' => [14, 14],
            'zero is clamped up' => [0, 1],
            'a negative is clamped up' => [-5, 1],
            'an absurd window is clamped down' => [9999, 180],
            'a non-integer falls back' => ['soon', 30],
        ];
    }

    #[DataProvider('windowSettings')]
    public function test_the_window_is_clamped_rather_than_trusted(mixed $stored, int $expected): void
    {
        Setting::updateOrCreate(['key' => 'booking_window_days'], ['value' => $stored, 'group' => 'booking']);

        $this->assertSame($expected, $this->slots->windowDays());
    }

    // -----------------------------------------------------------------
    // Timezone
    // -----------------------------------------------------------------

    public function test_slot_times_are_the_studios_wall_clock_and_are_stored_as_utc(): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY, ['opens_at' => '09:00:00', 'closes_at' => '10:00:00']);

        $slot = $this->slots->slotsOn('2026-09-02')[0];

        $this->assertSame('09:00', $slot['time']);
        // Riyadh is a fixed +03:00, so 09:00 local is 06:00 UTC. The
        // instant handed out is the UTC one; only the label is local.
        $this->assertSame('2026-09-02 06:00:00', $slot['at']->utc()->toDateTimeString());
    }

    public function test_a_late_slot_is_attributed_to_the_local_day_not_the_utc_one(): void
    {
        $this->noNotice();
        // 22:00 Riyadh is 19:00 UTC the same day — but a 02:00 Riyadh slot
        // would be the PREVIOUS day in UTC, which is the case that breaks
        // if anything reasons in the wrong zone.
        $this->openOn(BookingAvailability::WEDNESDAY, ['opens_at' => '01:00:00', 'closes_at' => '03:00:00']);

        $slot = $this->slots->slotsOn('2026-09-02')[0];

        $this->assertSame('01:00', $slot['time']);
        $this->assertSame('2026-09-01 22:00:00', $slot['at']->utc()->toDateTimeString());
    }

    // -----------------------------------------------------------------
    // resolveSlot — the only door a submitted time comes through
    // -----------------------------------------------------------------

    public function test_resolve_slot_returns_the_instant_for_a_real_slot(): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY);

        $this->assertSame(
            '2026-09-02 06:00:00',
            $this->slots->resolveSlot('2026-09-02', '09:00')?->utc()->toDateTimeString(),
        );
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function unresolvableSlots(): array
    {
        return [
            'a time the generator never produces' => ['2026-09-02', '09:17'],
            'a time outside opening hours' => ['2026-09-02', '18:00'],
            'a closed weekday' => ['2026-09-03', '09:00'],
            'a date in the past' => ['2026-08-26', '09:00'],
            'a malformed date' => ['02/09/2026', '09:00'],
            'a malformed time' => ['2026-09-02', '9am'],
            'nothing at all' => [null, null],
            'an injection attempt' => ["2026-09-02' OR 1=1--", '09:00'],
        ];
    }

    #[DataProvider('unresolvableSlots')]
    public function test_resolve_slot_refuses_anything_it_would_not_have_offered(?string $date, ?string $time): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY);
        $this->openOn(BookingAvailability::THURSDAY, ['is_open' => false]);

        // This is the whole guard: a crafted pair cannot become an
        // appointment, because the slot list is re-derived rather than the
        // submitted time being parsed and trusted.
        $this->assertNull($this->slots->resolveSlot($date, $time));
    }

    public function test_resolve_slot_refuses_a_slot_that_has_since_filled(): void
    {
        $this->noNotice();
        $this->openOn(BookingAvailability::WEDNESDAY, ['capacity' => 1]);

        Booking::factory()->at($this->localDate('2026-09-02')->setTime(9, 0)->utc())->create();

        $this->assertNull($this->slots->resolveSlot('2026-09-02', '09:00'));
    }
}
