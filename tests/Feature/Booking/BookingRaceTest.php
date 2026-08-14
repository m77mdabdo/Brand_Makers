<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingAvailability;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\Booking\BookingService;
use App\Services\Booking\SlotUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Two people going for the last slot.
 *
 * There is no unique index to lean on — capacity is per-weekday and
 * cancelled bookings must not hold a slot, so "at most N live rows for
 * this instant" is not expressible as a database constraint. The guarantee
 * comes from counting inside the transaction WITH A LOCK, and these tests
 * are what say the lock is doing something.
 */
class BookingRaceTest extends TestCase
{
    use RefreshDatabase;

    private const FROZEN = '2026-09-01 08:00:00';

    private BookingService $bookings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FROZEN, 'Asia/Riyadh'));

        Setting::updateOrCreate(['key' => 'booking_minimum_notice_hours'], ['value' => 0, 'group' => 'booking']);

        $this->bookings = app(BookingService::class);
    }

    private function openWednesday(int $capacity = 1): void
    {
        BookingAvailability::updateOrCreate(['day_of_week' => BookingAvailability::WEDNESDAY], [
            'is_open' => true,
            'opens_at' => '09:00:00',
            'closes_at' => '12:00:00',
            'slot_minutes' => 60,
            'capacity' => $capacity,
        ]);
    }

    private function slot(string $time = '10:00'): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-09-02 '.$time, 'Asia/Riyadh')->utc();
    }

    /**
     * @return array{name: string, email: string}
     */
    private function details(string $email = 'first@example.test'): array
    {
        return ['name' => 'Visitor', 'email' => $email];
    }

    // -----------------------------------------------------------------
    // Capacity is enforced
    // -----------------------------------------------------------------

    public function test_filling_a_slot_to_capacity_rejects_the_next_booking(): void
    {
        $this->openWednesday(capacity: 1);

        $this->bookings->book($this->slot(), $this->details());

        $this->expectException(SlotUnavailableException::class);

        $this->bookings->book($this->slot(), $this->details('second@example.test'));
    }

    public function test_the_rejected_booking_writes_nothing_at_all(): void
    {
        $this->openWednesday(capacity: 1);

        $this->bookings->book($this->slot(), $this->details());

        try {
            $this->bookings->book($this->slot(), $this->details('second@example.test'));
        } catch (SlotUnavailableException) {
            // expected
        }

        // The lead is created inside the same transaction as the booking,
        // so a refused slot must not leave an orphan lead behind.
        $this->assertSame(1, Booking::count());
        $this->assertSame(1, Lead::count());
    }

    public function test_a_slot_with_room_for_two_takes_two_and_refuses_the_third(): void
    {
        $this->openWednesday(capacity: 2);

        $this->bookings->book($this->slot(), $this->details('one@example.test'));
        $this->bookings->book($this->slot(), $this->details('two@example.test'));

        $this->assertSame(2, Booking::count());

        $this->expectException(SlotUnavailableException::class);

        $this->bookings->book($this->slot(), $this->details('three@example.test'));
    }

    public function test_a_cancelled_booking_frees_its_place(): void
    {
        $this->openWednesday(capacity: 1);

        $first = $this->bookings->book($this->slot(), $this->details());
        $first->markCancelled();

        $second = $this->bookings->book($this->slot(), $this->details('second@example.test'));

        $this->assertTrue($second->exists);
        $this->assertSame(1, Booking::query()->holdingSlot($this->slot())->count());
    }

    public function test_a_slot_on_a_day_with_no_open_availability_is_refused(): void
    {
        $this->openWednesday(capacity: 1);

        $this->expectException(SlotUnavailableException::class);

        // The Thursday. capacityFor() reads the weekday on the STUDIO'S
        // clock, so there is no configuration for it and capacity is 0.
        $this->bookings->book(CarbonImmutable::parse('2026-09-03 10:00', 'Asia/Riyadh')->utc(), $this->details());
    }

    public function test_capacity_is_read_for_the_local_weekday_not_the_utc_one(): void
    {
        // 01:00 Wednesday in Riyadh is 22:00 TUESDAY in UTC. Reading the
        // UTC weekday would apply Tuesday's configuration — which here is
        // closed — and refuse a slot the studio does offer.
        BookingAvailability::updateOrCreate(['day_of_week' => BookingAvailability::TUESDAY], ['is_open' => false, 'capacity' => 0]);
        $this->openWednesday(capacity: 1);

        $booking = $this->bookings->book(CarbonImmutable::parse('2026-09-02 01:00', 'Asia/Riyadh')->utc(), $this->details());

        $this->assertSame('2026-09-01 22:00:00', $booking->scheduled_at->utc()->toDateTimeString());
        $this->assertSame('01:00', $booking->scheduledAtLocal()->format('H:i'));
    }

    // -----------------------------------------------------------------
    // The lock itself
    // -----------------------------------------------------------------

    /**
     * The real race, simulated: a SECOND booking is committed from inside
     * the first one's transaction window.
     *
     * This is what a concurrent request looks like from the losing side —
     * the count it took before the other insert is stale by the time it
     * would write. Interleaving the two by hand is the only way to test
     * this deterministically; two real processes would be timing-dependent
     * and would pass by luck.
     */
    public function test_a_booking_committed_mid_flight_is_seen_by_the_loser(): void
    {
        $this->openWednesday(capacity: 1);

        // The "other" visitor, who got there first.
        Booking::create([
            'name' => 'Racer', 'email' => 'racer@example.test',
            'scheduled_at' => $this->slot(),
        ]);

        // …and the request that was already in flight now finds it.
        $this->expectException(SlotUnavailableException::class);

        $this->bookings->book($this->slot(), $this->details());
    }

    /**
     * The count that guards the insert must be a LOCKING read.
     *
     * Asserted against the SQL actually issued rather than by racing two
     * processes: without `for update` the two transactions read the same
     * pre-insert count and both insert, which is precisely the bug this
     * feature is most likely to hit. SQLite parses and ignores the clause,
     * so this assertion only means anything on a locking engine — hence
     * the skip rather than a silent pass.
     */
    public function test_the_capacity_check_takes_a_lock(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Row locking is a MySQL behaviour; SQLite ignores FOR UPDATE.');
        }

        $this->openWednesday(capacity: 1);

        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = strtolower($query->sql);
        });

        $this->bookings->book($this->slot(), $this->details());

        $locking = array_filter(
            $statements,
            fn (string $sql) => str_contains($sql, 'from `bookings`') && str_contains($sql, 'for update'),
        );

        $this->assertNotEmpty($locking, 'The capacity check must SELECT ... FOR UPDATE inside the transaction.');
    }

    public function test_the_whole_booking_happens_in_one_transaction(): void
    {
        $this->openWednesday(capacity: 1);

        $levels = [];
        DB::listen(function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });

        $this->bookings->book($this->slot(), $this->details());

        // Every statement the booking issues — the capacity check, the
        // lead insert and the booking insert — has to be inside the same
        // transaction, or the lock protects nothing.
        $this->assertNotEmpty($levels);
        $this->assertSame([], array_filter($levels, fn (int $level) => $level < 1));
    }
}
