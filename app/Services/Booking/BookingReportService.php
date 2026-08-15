<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Support\CalendarDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reads bookings for the dashboard.
 *
 * The filter set lives here rather than in the controller because the
 * list, the count and the agenda all consume it, and because both dates
 * reach a query builder.
 *
 * EVERY DATE FILTER IS INTERPRETED ON THE STUDIO'S CLOCK. An admin typing
 * "2026-09-02" means that day in Riyadh, not a UTC window that starts at
 * 03:00 local — which would silently drop the first three hours of
 * appointments from a day's agenda.
 */
class BookingReportService
{
    public function __construct(private readonly BookingSlotService $slots) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters): Builder
    {
        return Booking::query()
            ->with(['service', 'lead:id,type'])
            ->when(
                filled($filters['status'] ?? null),
                fn (Builder $query) => $query->where('status', $filters['status'])
            )
            ->when(
                filled($filters['service_id'] ?? null),
                fn (Builder $query) => $query->where('service_id', $filters['service_id'])
            )
            ->when(
                filled($filters['date_from'] ?? null),
                fn (Builder $query) => $query->where('scheduled_at', '>=', $this->startOfLocalDay($filters['date_from']))
            )
            ->when(
                filled($filters['date_to'] ?? null),
                // endOfDay, so "to the 2nd" includes the whole of the 2nd
                // rather than stopping at midnight.
                fn (Builder $query) => $query->where('scheduled_at', '<=', $this->endOfLocalDay($filters['date_to']))
            )
            ->orderBy('scheduled_at');
    }

    /**
     * Only the keys the filter form owns, whitelisted before they reach a
     * builder.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function filtersFrom(array $input): array
    {
        $filters = array_intersect_key($input, array_flip(['status', 'service_id', 'date_from', 'date_to']));

        if (! in_array($filters['status'] ?? null, Booking::STATUSES, true)) {
            unset($filters['status']);
        }

        if (! is_numeric($filters['service_id'] ?? null)) {
            unset($filters['service_id']);
        }

        foreach (['date_from', 'date_to'] as $key) {
            $value = $filters[$key] ?? null;

            // Strict: the shape alone would let "2026-13-45" through to
            // a parse that throws. See CalendarDate.
            if (! CalendarDate::isValid($value)) {
                unset($filters[$key]);
            }
        }

        return array_filter($filters, fn ($value) => filled($value));
    }

    /**
     * One day's appointments, in order — the agenda view.
     *
     * Cancelled ones are included rather than hidden: an admin looking at
     * a day needs to see that 10:00 was cancelled, not an empty gap they
     * might double-book into by hand.
     *
     * @return Collection<int, Booking>
     */
    public function agendaFor(CarbonImmutable|string $date): Collection
    {
        $day = $date instanceof CarbonImmutable
            ? $date->setTimezone($this->slots->timezone())->startOfDay()
            : (CalendarDate::parse($date, $this->slots->timezone())
                ?? CarbonImmutable::now($this->slots->timezone())->startOfDay());

        return Booking::query()
            ->with('service')
            ->whereBetween('scheduled_at', [$day->utc(), $day->endOfDay()->utc()])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * How many bookings are waiting for a decision — the sidebar badge.
     *
     * Pending only: confirmed and completed need no action, and cancelled
     * ones are closed. Rides bookings_status_scheduled_at_index.
     */
    public function pendingCount(): int
    {
        return Booking::query()->status(Booking::STATUS_PENDING)->count();
    }

    private function startOfLocalDay(string $date): CarbonImmutable
    {
        return CalendarDate::parse($date, $this->slots->timezone())->utc();
    }

    private function endOfLocalDay(string $date): CarbonImmutable
    {
        return CalendarDate::parse($date, $this->slots->timezone())->endOfDay()->utc();
    }
}
