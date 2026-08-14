<?php

namespace App\Services\Booking;

use App\Models\Booking;

/**
 * The only thing that moves a booking's status.
 *
 * `status` is guarded out of Booking::$fillable, so no payload can set it;
 * this class is where the rules about which move is legal live, and it is
 * also where the notification hook fires, so a status change can never
 * happen without the corresponding message being considered.
 *
 * THE ALLOWED MOVES, and why the graph is not "anything to anything":
 *
 *   pending   → confirmed | cancelled
 *   confirmed → completed | cancelled
 *   completed → (nothing)
 *   cancelled → (nothing)
 *
 * Completed and cancelled are terminal because both are statements about
 * something that has already happened. Re-opening a cancelled booking
 * would silently re-take a slot that has since been given to somebody
 * else — the capacity check ran when it was cancelled, not now — and
 * un-completing an appointment that took place is not a thing that can be
 * true. An admin who needs either can take a new booking, which goes
 * through the capacity check like any other.
 *
 * Confirming does NOT re-check capacity: the slot was reserved when the
 * booking was taken and has been held ever since (pending counts as
 * holding), so there is nothing to re-check.
 */
class BookingStatusService
{
    /**
     * status => the statuses it may move to.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        Booking::STATUS_PENDING => [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED],
        Booking::STATUS_CONFIRMED => [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED],
        Booking::STATUS_COMPLETED => [],
        Booking::STATUS_CANCELLED => [],
    ];

    public function __construct(private readonly BookingNotifier $notifier) {}

    public function canTransition(Booking $booking, string $to): bool
    {
        return in_array($to, self::allowedFor($booking), true);
    }

    /**
     * @return list<string>
     */
    public static function allowedFor(Booking $booking): array
    {
        return self::TRANSITIONS[$booking->status] ?? [];
    }

    /**
     * Moves the booking, or returns false when the move is not allowed.
     *
     * False rather than an exception: the only caller is a dashboard
     * action, and an admin clicking a stale button deserves a message, not
     * a 500.
     */
    public function transition(Booking $booking, string $to): bool
    {
        if (! $this->canTransition($booking, $to)) {
            return false;
        }

        $moved = match ($to) {
            Booking::STATUS_CONFIRMED => $booking->markConfirmed(),
            Booking::STATUS_COMPLETED => $booking->markCompleted(),
            Booking::STATUS_CANCELLED => $booking->markCancelled(),
            default => false,
        };

        if (! $moved) {
            return false;
        }

        // After the save, never before: a notification about a state the
        // database does not hold would be worse than none.
        match ($to) {
            Booking::STATUS_CONFIRMED => $this->notifier->bookingConfirmed($booking),
            Booking::STATUS_CANCELLED => $this->notifier->bookingCancelled($booking),
            default => null,
        };

        return true;
    }
}
