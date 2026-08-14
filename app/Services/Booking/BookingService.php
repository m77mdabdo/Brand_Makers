<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingAvailability;
use App\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Takes a booking, and refuses to take one twice.
 *
 * THE DOUBLE-BOOKING PROBLEM. There is no unique index to lean on:
 * capacity is configurable per weekday and cancelled bookings must not
 * hold a slot, so the rule is "at most N rows for this instant, not
 * counting cancelled ones" — which no database constraint can express.
 *
 * So the count and the insert happen inside ONE transaction, and the count
 * takes a lock:
 *
 *   SELECT ... FOR UPDATE   (lockForUpdate)
 *   ...decide...
 *   INSERT
 *
 * Two requests arriving together therefore serialise: the first holds the
 * lock until it commits, the second reads the count only after that insert
 * is visible and sees the slot is full. Without the lock both would read
 * the same pre-insert count and both would insert — the classic
 * check-then-act race, and the one this feature is most likely to hit
 * because everybody wants the same 10:00 on Sunday.
 *
 * SQLite has no row-level locking and ignores lockForUpdate, which is why
 * the race test also runs against MySQL — see BookingRaceTest.
 */
class BookingService
{
    public function __construct(private readonly BookingSlotService $slots) {}

    /**
     * Creates the booking and the lead it belongs to, or throws.
     *
     * @param  array{name: string, email: string, phone?: ?string, notes?: ?string, service_id?: ?int}  $details
     * @param  array<string, mixed>  $attribution  as resolved by AttributionService
     *
     * @throws SlotUnavailableException when the slot filled up first
     */
    public function book(CarbonImmutable $slotUtc, array $details, array $attribution = []): Booking
    {
        return DB::transaction(function () use ($slotUtc, $details, $attribution) {
            $capacity = $this->capacityFor($slotUtc);

            if ($capacity < 1) {
                throw new SlotUnavailableException('No availability is configured for that slot.');
            }

            /*
             * lockForUpdate on the COUNT, inside the transaction.
             *
             * This is the whole mechanism. It locks the rows already
             * holding this instant (and, on InnoDB, the gap they sit in)
             * so a second transaction counting the same slot blocks here
             * until this one commits.
             */
            $held = Booking::query()
                ->holdingSlot($slotUtc)
                ->lockForUpdate()
                ->count();

            if ($held >= $capacity) {
                throw new SlotUnavailableException('That slot has just been taken.');
            }

            /*
             * The lead first, so the booking can point at it.
             *
             * A booking IS a lead as far as the business is concerned —
             * somebody who wants to be contacted — so it goes through the
             * same table, with the same attribution, and shows up in the
             * same enquiry queue as everything else. Recording it only as
             * a booking would leave it out of every conversion figure the
             * reports compute.
             */
            $lead = Lead::create([
                'full_name' => $details['name'],
                'email' => $details['email'],
                'phone' => $details['phone'] ?? null,
                'message' => $details['notes'] ?? null,
                'requested_service_id' => $details['service_id'] ?? null,
                ...$attribution,
                'type' => Lead::TYPE_BOOKING,
                'consent_given' => true,
                'consented_at' => now(),
            ]);

            return Booking::create([
                'name' => $details['name'],
                'email' => $details['email'],
                'phone' => $details['phone'] ?? null,
                'service_id' => $details['service_id'] ?? null,
                'scheduled_at' => $slotUtc,
                'notes' => $details['notes'] ?? null,
                'lead_id' => $lead->getKey(),
                // status is guarded and defaults to pending: a booking
                // taken from the public site is a request, not a
                // confirmed appointment. See BookingStatusService.
            ]);
        });
    }

    /**
     * The configured capacity for the weekday this instant falls on, read
     * on the STUDIO'S clock — a slot at 22:00 UTC is the next day in
     * Riyadh, and reading the wrong weekday's capacity would silently
     * apply Thursday's rules to a Friday.
     */
    private function capacityFor(CarbonImmutable $slotUtc): int
    {
        $local = $slotUtc->setTimezone($this->slots->timezone());

        return (int) (BookingAvailability::query()
            ->open()
            ->forDate($local)
            ->value('capacity') ?? 0);
    }
}
