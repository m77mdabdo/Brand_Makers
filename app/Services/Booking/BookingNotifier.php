<?php

namespace App\Services\Booking;

use App\Models\Booking;
use Illuminate\Support\Facades\Log;

/**
 * THE SEAM WHERE CONFIRMATION MESSAGES WILL PLUG IN. Nothing is sent yet,
 * and that is deliberate.
 *
 * WHY IT EXISTS AS A CLASS RATHER THAN A TODO COMMENT. The two call sites
 * that will need it — a booking being taken, and a booking being confirmed
 * by an admin — are written in this build. Leaving them calling a real
 * object that currently does nothing means adding the transport later is a
 * change to ONE file, rather than a hunt for the places a message should
 * have gone.
 *
 * WHAT IS DELIBERATELY NOT HERE.
 *
 *   No mail. config/mail.php still points at the framework default, and a
 *   Mailable sent through an unconfigured transport either throws in the
 *   visitor's face or silently vanishes. Neither is better than not
 *   claiming to have sent anything.
 *
 *   No WhatsApp. That needs a Business API account, a template approved by
 *   Meta, and a number — none of which exist yet. Wiring a provider SDK
 *   against credentials nobody has would be code that has never run.
 *
 * WHAT THE VISITOR IS TOLD MEANWHILE. The confirmation screen says the
 * studio will be in touch to confirm — it does not say an email is on its
 * way. That sentence stays true precisely because nothing here pretends
 * otherwise.
 *
 * TO IMPLEMENT: switch config('booking.notifications_enabled') on, and
 * replace the two log lines below with a queued Mailable and a WhatsApp
 * job. Queue them rather than sending inline — a slow third-party API must
 * not hold a visitor's form submission open. The booking is already
 * committed by the time these are called, so a failure to notify must
 * never roll one back.
 */
class BookingNotifier
{
    /**
     * A visitor has just booked. Their message says "received, we will
     * confirm"; the studio's says "somebody booked".
     */
    public function bookingReceived(Booking $booking): void
    {
        $this->record('booking.received', $booking);
    }

    /**
     * An admin has confirmed the appointment. This is the message the
     * visitor is actually waiting for, and the one with the details they
     * need on the day.
     */
    public function bookingConfirmed(Booking $booking): void
    {
        $this->record('booking.confirmed', $booking);
    }

    /**
     * An admin has cancelled. Worth a message precisely because it is the
     * outcome a visitor will otherwise turn up for.
     */
    public function bookingCancelled(Booking $booking): void
    {
        $this->record('booking.cancelled', $booking);
    }

    /**
     * Until a transport exists, the intent is written to the log.
     *
     * Debug level, so it stays out of production error reporting: this is
     * a note that a message WOULD have been sent, not a problem. It also
     * makes the seam visible in local development — you can see exactly
     * which events would have produced messages.
     */
    private function record(string $event, Booking $booking): void
    {
        if (! config('booking.notifications_enabled')) {
            Log::debug('Booking notification not sent (no transport configured).', [
                'event' => $event,
                'reference' => $booking->reference,
                // The reference and the id only. A log line is the wrong
                // place for a visitor's email address, and this one is
                // written on every booking.
                'booking_id' => $booking->getKey(),
            ]);

            return;
        }

        // TODO: dispatch the queued Mailable and the WhatsApp job here.
        Log::debug('Booking notification requested.', ['event' => $event, 'reference' => $booking->reference]);
    }
}
