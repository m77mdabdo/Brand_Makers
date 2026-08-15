<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingAvailability;
use App\Models\BookingBlackoutDate;
use App\Services\Cms\SettingsRepository;
use App\Support\CalendarDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What can actually be booked, and when.
 *
 * THE TIMEZONE RULE, which every method here obeys and nothing outside
 * here has to think about:
 *
 *   - booking_availability.opens_at / closes_at are wall-clock times in the
 *     STUDIO'S zone (config('booking.timezone')). "09:00" means nine in
 *     Riyadh, whatever the server is set to.
 *   - blackout dates are calendar dates in that same zone.
 *   - bookings.scheduled_at is a UTC instant, like every other timestamp in
 *     the schema.
 *
 * So slots are generated in local wall-clock and handed out as UTC
 * instants. A caller never converts; it either asks for slots or hands one
 * back.
 *
 * COST. Generating a 30-day window is one read of the seven availability
 * rows, one read of the blackout dates in range, and ONE grouped count of
 * the bookings in range — not a query per day and certainly not per slot.
 */
class BookingSlotService
{
    /**
     * Bounds on the two settings this reads. Both reach date arithmetic
     * that a visitor sees, so a hand-edited row cannot open the calendar
     * for three years or demand a year's notice.
     */
    private const MAX_WINDOW_DAYS = 180;

    private const MAX_NOTICE_HOURS = 720;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function timezone(): string
    {
        return config('booking.timezone');
    }

    /**
     * Whether booking is offered at all.
     *
     * False when no weekday is open — the state of an install where nobody
     * has configured availability yet. The public page says booking is not
     * available rather than rendering an empty calendar, which is the same
     * no-invented-content rule the estimator and the packages page follow.
     */
    public function isConfigured(): bool
    {
        return BookingAvailability::query()->open()->exists();
    }

    /**
     * How far ahead the calendar opens. Clamped, never trusted.
     */
    public function windowDays(): int
    {
        return max(1, min(self::MAX_WINDOW_DAYS, $this->settings->integer('booking_window_days', 30)));
    }

    /**
     * How much warning the studio needs before the next bookable slot.
     *
     * Zero is legitimate — a studio may be happy to take a booking for an
     * hour's time — so this clamps at 0 rather than 1.
     */
    public function minimumNoticeHours(): int
    {
        return max(0, min(self::MAX_NOTICE_HOURS, $this->settings->integer('booking_minimum_notice_hours', 24)));
    }

    /**
     * The bookable calendar: every day in the window that has at least one
     * free slot, each with its remaining slots.
     *
     * Days with nothing left are omitted entirely rather than rendered
     * empty — a visitor scanning for a date should not have to open one to
     * discover it is full.
     *
     * @return Collection<int, array{date: CarbonImmutable, slots: list<array{time: string, at: CarbonImmutable, remaining: int}>}>
     */
    public function availableDays(): Collection
    {
        if (! $this->isConfigured()) {
            return collect();
        }

        $today = CarbonImmutable::now($this->timezone())->startOfDay();
        $lastDay = $today->addDays($this->windowDays());

        $availability = BookingAvailability::query()->open()->get()->keyBy('day_of_week');
        $blackouts = $this->blackoutsBetween($today, $lastDay);
        $taken = $this->bookingCountsBetween($today, $lastDay->endOfDay());

        $days = collect();

        for ($date = $today; $date->lessThanOrEqualTo($lastDay); $date = $date->addDay()) {
            $config = $availability->get($date->dayOfWeek);

            if ($config === null || $blackouts->contains($date->toDateString())) {
                continue;
            }

            $slots = $this->slotsForDay($date, $config, $taken);

            if ($slots !== []) {
                $days->push(['date' => $date, 'slots' => $slots]);
            }
        }

        return $days;
    }

    /**
     * The free slots on ONE day, or an empty list when the day is closed,
     * blacked out, in the past or fully booked.
     *
     * @return list<array{time: string, at: CarbonImmutable, remaining: int}>
     */
    public function slotsOn(CarbonImmutable|string $date): array
    {
        $date = $date instanceof CarbonImmutable
            ? $date->setTimezone($this->timezone())->startOfDay()
            : CalendarDate::parse($date, $this->timezone());

        // Not a real calendar date at all.
        if ($date === null) {
            return [];
        }

        $config = BookingAvailability::query()->open()->forDate($date)->first();

        if ($config === null || BookingBlackoutDate::blocks($date)) {
            return [];
        }

        if (! $this->withinWindow($date)) {
            return [];
        }

        return $this->slotsForDay(
            $date,
            $config,
            $this->bookingCountsBetween($date, $date->endOfDay()),
        );
    }

    /**
     * Turns a submitted date + time into the UTC instant it refers to, or
     * null when that pair is not a slot this service would have offered.
     *
     * This is the ONLY way a request-supplied time becomes a bookable
     * instant. It re-derives the whole slot list rather than parsing the
     * submitted time and trusting it, so a hand-crafted "03:17 on a
     * Friday" cannot become an appointment.
     */
    public function resolveSlot(?string $date, ?string $time): ?CarbonImmutable
    {
        if (! is_string($date) || ! is_string($time)) {
            return null;
        }

        // A STRICT date check, not a shape check: "2026-13-45" matches
        // the shape and is not a date. See CalendarDate.
        if (! CalendarDate::isValid($date) || preg_match('/^\d{2}:\d{2}$/', $time) !== 1) {
            return null;
        }

        foreach ($this->slotsOn($date) as $slot) {
            if ($slot['time'] === $time) {
                return $slot['at'];
            }
        }

        return null;
    }

    /**
     * Whether a specific instant still has room.
     *
     * Used by BookingService inside its transaction as the final check —
     * see there for why the answer here is not enough on its own.
     */
    public function hasCapacity(CarbonImmutable $slotUtc): bool
    {
        return $this->remainingAt($slotUtc) > 0;
    }

    /**
     * Free places at an instant: the configured capacity for that weekday
     * minus the bookings already holding it.
     */
    public function remainingAt(CarbonImmutable $slotUtc): int
    {
        $local = $slotUtc->setTimezone($this->timezone());

        $config = BookingAvailability::query()->open()->forDate($local)->first();

        if ($config === null) {
            return 0;
        }

        $held = Booking::query()->holdingSlot($slotUtc)->count();

        return max(0, $config->capacity - $held);
    }

    /**
     * @param  Collection<string, int>  $taken  "Y-m-d H:i" (UTC) => bookings held
     * @return list<array{time: string, at: CarbonImmutable, remaining: int}>
     */
    private function slotsForDay(CarbonImmutable $date, BookingAvailability $config, Collection $taken): array
    {
        $earliest = CarbonImmutable::now($this->timezone())->addHours($this->minimumNoticeHours());

        $slots = [];

        foreach ($config->slots() as $time) {
            [$hour, $minute] = array_map('intval', explode(':', $time));

            $localStart = $date->setTime($hour, $minute);

            // The notice period. A slot that is already inside it is not
            // offered at all rather than offered and then refused.
            if ($localStart->lessThan($earliest)) {
                continue;
            }

            $utc = $localStart->utc();
            $held = $taken->get($utc->format('Y-m-d H:i'), 0);
            $remaining = $config->capacity - $held;

            if ($remaining < 1) {
                continue;
            }

            $slots[] = ['time' => $time, 'at' => $utc, 'remaining' => $remaining];
        }

        return $slots;
    }

    /**
     * One grouped count for the whole range, keyed by instant.
     *
     * Cancelled bookings are excluded by scopeHoldingSlot, so a cancelled
     * appointment releases its place immediately rather than holding the
     * calendar hostage.
     *
     * @return Collection<string, int>
     */
    private function bookingCountsBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Booking::query()
            ->whereIn('status', Booking::SLOT_HOLDING_STATUSES)
            ->whereBetween('scheduled_at', [$from->utc(), $to->utc()])
            ->get(['scheduled_at'])
            ->groupBy(fn (Booking $booking) => CarbonImmutable::parse($booking->scheduled_at)->utc()->format('Y-m-d H:i'))
            ->map(fn (Collection $group) => $group->count());
    }

    /**
     * @return Collection<int, string>
     */
    private function blackoutsBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return BookingBlackoutDate::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString());
    }

    /**
     * Today through today + windowDays, inclusive, on the studio's clock.
     */
    private function withinWindow(CarbonImmutable $date): bool
    {
        $today = CarbonImmutable::now($this->timezone())->startOfDay();

        return $date->greaterThanOrEqualTo($today)
            && $date->lessThanOrEqualTo($today->addDays($this->windowDays()));
    }
}
