<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Service;
use App\Services\Booking\BookingDate;
use App\Services\Booking\BookingReportService;
use App\Services\Booking\BookingSlotService;
use App\Services\Booking\BookingStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingReportService $report,
        private readonly BookingStatusService $statuses,
        private readonly BookingSlotService $slots,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->report->filtersFrom($request->all());

        return view('dashboard.bookings.index', [
            'bookings' => $this->report->query($filters)->paginate(25)->withQueryString(),
            'filters' => $filters,
            'statuses' => Booking::STATUSES,
            'services' => Service::query()->withTrashed()->ordered()->get(),
            'timezone' => $this->slots->timezone(),
        ]);
    }

    /**
     * The day view.
     *
     * A separate action rather than a mode on the index: it answers a
     * different question ("what does Tuesday look like") and wants a
     * different shape — every appointment in order including the cancelled
     * ones, not a filtered page of them.
     */
    public function agenda(Request $request): View
    {
        $date = $this->requestedDate($request);

        return view('dashboard.bookings.agenda', [
            'date' => $date,
            'bookings' => $this->report->agendaFor($date),
            'previousDate' => $date->subDay()->toDateString(),
            'nextDate' => $date->addDay()->toDateString(),
            'timezone' => $this->slots->timezone(),
        ]);
    }

    public function show(Booking $booking): View
    {
        $booking->load(['service', 'lead']);

        return view('dashboard.bookings.show', [
            'booking' => $booking,
            // The moves this booking can actually make, so the screen
            // offers no button that would be refused.
            'allowedTransitions' => BookingStatusService::allowedFor($booking),
            'timezone' => $this->slots->timezone(),
        ]);
    }

    /**
     * Moves a booking's status.
     *
     * `status` is guarded out of Booking::$fillable and this endpoint does
     * not write it directly either — BookingStatusService owns the graph
     * and fires the notification hook, so a move can never happen without
     * the message being considered.
     */
    public function transition(Request $request, Booking $booking): RedirectResponse
    {
        $to = $request->input('status');

        if (! is_string($to) || ! $this->statuses->transition($booking, $to)) {
            /*
             * A refusal, not a 422. The only way to reach this is a stale
             * screen — two admins acting on the same booking, or a back
             * button — and telling them the move is no longer available is
             * more useful than a validation error about a field they never
             * saw.
             */
            return back()->with('error', __('dashboard.bookings.transition_refused'));
        }

        return back()->with('status', __('dashboard.bookings.transition_done.'.$to));
    }

    /**
     * The requested day, on the studio's clock, defaulting to today.
     *
     * Anything that is not a plain Y-m-d falls back rather than erroring:
     * the date arrives from a query string that a person may well have
     * typed.
     */
    private function requestedDate(Request $request): CarbonImmutable
    {
        // Strictly parsed, because "2026-13-45" matches the shape of a
        // date and is not one — and this value arrives from a query string
        // a person may well have typed.
        return BookingDate::parse($request->query('date'), $this->slots->timezone())
            ?? CarbonImmutable::now($this->slots->timezone())->startOfDay();
    }
}
