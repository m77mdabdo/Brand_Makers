<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreBlackoutDateRequest;
use App\Http\Requests\Dashboard\UpdateBookingAvailabilityRequest;
use App\Models\BookingBlackoutDate;
use App\Services\Booking\BookingAvailabilityService;
use App\Services\Booking\BookingSlotService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * When the studio takes appointments.
 *
 * The weekly window and the one-off closures are edited on the same
 * screen because they answer one question between them — "when are we
 * open" — and an admin closing for Eid should not have to know which of
 * two screens expresses that.
 */
class BookingAvailabilityController extends Controller
{
    public function __construct(
        private readonly BookingAvailabilityService $availability,
        private readonly BookingSlotService $slots,
    ) {}

    public function edit(): View
    {
        return view('dashboard.booking-availability.edit', [
            'week' => $this->availability->week(),
            // Upcoming first: a blackout in the past affects nothing the
            // generator produces and is only kept as a record.
            'blackouts' => BookingBlackoutDate::query()
                ->orderByDesc('date')
                ->paginate(20),
            'today' => CarbonImmutable::now($this->slots->timezone()),
            'timezone' => $this->slots->timezone(),
            // Read-only here, editable in Settings. Shown because they
            // change what this screen's configuration actually produces,
            // and an admin wondering why tomorrow is missing needs to see
            // the notice period without hunting for it.
            'windowDays' => $this->slots->windowDays(),
            'noticeHours' => $this->slots->minimumNoticeHours(),
        ]);
    }

    public function update(UpdateBookingAvailabilityRequest $request): RedirectResponse
    {
        $this->availability->update($request->validated('days'));

        return redirect()
            ->route('dashboard.booking-availability.edit')
            ->with('status', __('dashboard.flash.updated'));
    }

    public function storeBlackout(StoreBlackoutDateRequest $request): RedirectResponse
    {
        $this->availability->blackout(
            $request->validated('date'),
            $request->validated('reason'),
        );

        return redirect()
            ->route('dashboard.booking-availability.edit')
            ->with('status', __('dashboard.flash.created'));
    }

    public function destroyBlackout(BookingBlackoutDate $blackoutDate): RedirectResponse
    {
        $this->availability->removeBlackout($blackoutDate);

        return redirect()
            ->route('dashboard.booking-availability.edit')
            ->with('status', __('dashboard.flash.deleted'));
    }
}
