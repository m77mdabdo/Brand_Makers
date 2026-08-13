<?php

namespace App\Services\Crm;

use App\Models\Lead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The Contact Messages inbox.
 *
 * There is no contact_messages table: a contact submission IS a `leads`
 * row with type = 'contact'. Splitting them out would fork attribution
 * and the leads report for no gain — this service is a narrow VIEW of
 * that table, not a second store.
 */
class ContactMessageService
{
    /**
     * @param  'all'|'unhandled'|'handled'  $filter
     */
    public function paginate(string $filter = 'all', int $perPage = 20): LengthAwarePaginator
    {
        return Lead::query()
            ->contactMessages()
            ->when($filter === 'unhandled', fn ($query) => $query->unhandled())
            ->when($filter === 'handled', fn ($query) => $query->where('is_handled', true))
            // Unread first, then newest. The inbox is a work queue, not a
            // chronological log.
            ->orderBy('is_handled')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Drives the sidebar badge, so it runs on EVERY dashboard page
     * render — one indexed COUNT, covered by
     * leads_type_is_handled_index.
     */
    public function unhandledCount(): int
    {
        return Lead::query()->contactMessages()->unhandled()->count();
    }

    /**
     * is_handled is guarded out of $fillable (see Lead), so this goes
     * through the model's own transition rather than mass assignment —
     * the same route that stops a public submission marking its own
     * enquiry as already dealt with.
     */
    public function setHandled(Lead $lead, bool $handled): Lead
    {
        return tap($lead, fn (Lead $l) => $handled ? $l->markHandled() : $l->markUnhandled());
    }
}
