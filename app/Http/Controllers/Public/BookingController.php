<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreBookingRequest;
use App\Models\Service;
use App\Services\Analytics\VisitorSessionService;
use App\Services\Booking\BookingNotifier;
use App\Services\Booking\BookingService;
use App\Services\Booking\BookingSlotService;
use App\Services\Booking\SlotUnavailableException;
use App\Services\Marketing\AttributionService;
use App\Support\Honeypot;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The public booking flow: pick a topic, pick a slot, leave your details.
 *
 * Availability is generated server-side and handed to the page whole — the
 * date and slot pickers are a view over that data, not a source of it. On
 * submission the slot is re-derived from the same generator, so a stale
 * page or a crafted payload cannot book a time the studio never offered.
 */
class BookingController extends Controller
{
    public function __construct(
        private readonly BookingSlotService $slots,
        private readonly BookingService $bookings,
        private readonly AttributionService $attribution,
        private readonly VisitorSessionService $visitorSessions,
        private readonly BookingNotifier $notifier,
    ) {}

    public function show(): View
    {
        return view('public.booking', [
            'isConfigured' => $this->slots->isConfigured(),
            'days' => $this->slots->availableDays(),
            'services' => Service::query()->active()->ordered()->get(),
            'timezone' => $this->slots->timezone(),
            'noticeHours' => $this->slots->minimumNoticeHours(),
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        // Before any write, and a caught bot gets the same redirect and
        // flash a real submission gets.
        if (Honeypot::tripped($request)) {
            return $this->submitted();
        }

        $slot = $this->slots->resolveSlot($request->validated('date'), $request->validated('time'));

        /*
         * Not a slot at all — a stale page, or a payload naming a time the
         * generator would never produce. Distinct from the race below: this
         * one means "that was never on offer".
         */
        if ($slot === null) {
            return $this->rejected(__('site.booking.slot_gone'));
        }

        try {
            $booking = $this->bookings->book(
                $slot,
                [
                    ...$request->safe()->only(['name', 'email', 'phone', 'notes']),
                    'service_id' => $request->validated('service_id'),
                ],
                $this->attribution->resolve($request),
            );
        } catch (SlotUnavailableException) {
            /*
             * The race. Somebody else committed for this slot between the
             * page rendering and this request reaching the lock. The
             * visitor did nothing wrong, so they get a plain "just taken,
             * please pick another" and the form back — not a validation
             * error implying their input was bad.
             */
            return $this->rejected(__('site.booking.slot_taken'));
        }

        // After the commit. A booking that exists but was not announced is
        // recoverable; one announced and then rolled back is not.
        $this->notifier->bookingReceived($booking);

        $this->visitorSessions->recordConversion($request, 'booking_submitted', $booking->lead);

        /*
         * The reference travels in the FLASH, not the URL.
         *
         * Putting it in a query string would leave it in the visitor's
         * history and in any referrer header the next click sends, and
         * would let anyone construct a confirmation screen for a reference
         * they guessed. Flashed, it is shown exactly once, to the person
         * who just booked.
         */
        return redirect()
            ->to(lroute('booking').'#booking-confirmed')
            ->with('status', __('site.flash.booking_submitted'))
            ->with('booking_reference', $booking->reference)
            ->with('booking_when', $booking->scheduledAtLocal()->translatedFormat('l j F Y — H:i'));
    }

    /**
     * The honeypot's silent success. Identical to a real submission's
     * redirect and flash; only the reference is absent, which nothing a
     * bot can observe depends on.
     */
    private function submitted(): RedirectResponse
    {
        return redirect()
            ->to(lroute('booking').'#booking-confirmed')
            ->with('status', __('site.flash.booking_submitted'));
    }

    /**
     * withInput() so the visitor does not retype their details after a
     * slot they picked turned out to be gone.
     */
    private function rejected(string $message): RedirectResponse
    {
        return redirect()
            ->to(lroute('booking').'#booking-form')
            ->withInput()
            ->with('error', $message);
    }
}
