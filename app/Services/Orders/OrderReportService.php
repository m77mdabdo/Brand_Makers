<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads orders for the dashboard.
 *
 * The filter set lives here rather than in the controller because both the
 * list and its count consume it, and because every value in it reaches a
 * query builder.
 */
class OrderReportService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters): Builder
    {
        return Order::query()
            ->with(['package', 'lead:id,type'])
            ->when(
                filled($filters['status'] ?? null),
                fn (Builder $query) => $query->where('status', $filters['status'])
            )
            ->when(
                filled($filters['date_from'] ?? null),
                fn (Builder $query) => $query->whereDate('placed_at', '>=', $filters['date_from'])
            )
            ->when(
                filled($filters['date_to'] ?? null),
                // whereDate, so "to today" includes everything today
                // rather than stopping at midnight.
                fn (Builder $query) => $query->whereDate('placed_at', '<=', $filters['date_to'])
            )
            ->when(
                filled($filters['reference'] ?? null),
                /*
                 * A prefix match on the reference, for the dashboard only.
                 *
                 * Safe HERE and nowhere else: this endpoint is behind
                 * auth+admin, so searching by reference reveals nothing to
                 * anyone who could not already list every order. The
                 * PUBLIC portal has no such search by design — see
                 * OrderLookupService.
                 */
                fn (Builder $query) => $query->where('reference', 'like', strtoupper($filters['reference']).'%')
            )
            ->orderByDesc('placed_at');
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
        $filters = array_intersect_key($input, array_flip(['status', 'date_from', 'date_to', 'reference']));

        if (! in_array($filters['status'] ?? null, Order::STATUSES, true)) {
            unset($filters['status']);
        }

        foreach (['date_from', 'date_to'] as $key) {
            $value = $filters[$key] ?? null;

            if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                unset($filters[$key]);
            }
        }

        // The reference goes into a LIKE. Restricted to the alphabet
        // references are actually made of, so neither a wildcard nor an
        // escape can reach the pattern.
        $reference = $filters['reference'] ?? null;

        if (! is_string($reference) || preg_match('/^[A-Za-z0-9-]{1,32}$/', $reference) !== 1) {
            unset($filters['reference']);
        }

        return array_filter($filters, fn ($value) => filled($value));
    }

    /**
     * Orders still needing work — the sidebar badge.
     *
     * Open rather than pending: an order in production is as much a live
     * commitment as one nobody has started, and the badge is a count of
     * what the studio owes people. Rides orders_status_placed_at_index.
     */
    public function openCount(): int
    {
        return Order::query()->open()->count();
    }
}
