<?php

namespace App\Services\Analytics;

use App\Models\VisitorSession;
use App\Support\CalendarDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reads visitor_sessions for the dashboard.
 *
 * The filter set lives here rather than in the controller because THREE
 * things consume it — the index listing, the filtered JSON export, and the
 * count shown above the table. An export that quietly disagreed with the
 * table it came from would be the worst kind of bug: silent, and only
 * noticed once somebody had acted on the file.
 */
class VisitorSessionReportService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters): Builder
    {
        return VisitorSession::query()
            // Only the columns the listing and the export are allowed to
            // read. Selecting the whole lead would pull name, email and
            // phone into memory on a screen that must never print them.
            ->with('lead:id,type,created_at,deleted_at')
            ->when(
                ($filters['converted'] ?? null) === 'converted',
                fn (Builder $query) => $query->converted()
            )
            ->when(
                ($filters['converted'] ?? null) === 'not_converted',
                fn (Builder $query) => $query->notConverted()
            )
            ->when(
                filled($filters['source'] ?? null),
                /*
                 * A JSON path rather than a column: the source is part of
                 * the attribution snapshot, and duplicating it into a
                 * column of its own would create a second thing to keep in
                 * step with the touch cookies. The table is bounded by the
                 * retention window, so an unindexed path here stays cheap.
                 */
                fn (Builder $query) => $query->where('attribution->latest_touch->utm_source', $filters['source'])
            )
            ->when(
                filled($filters['device'] ?? null),
                fn (Builder $query) => $query->where('device_type', $filters['device'])
            )
            ->when(
                filled($filters['date_from'] ?? null),
                fn (Builder $query) => $query->whereDate('started_at', '>=', $filters['date_from'])
            )
            ->when(
                filled($filters['date_to'] ?? null),
                // whereDate, so "to today" means everything today rather
                // than stopping at midnight.
                fn (Builder $query) => $query->whereDate('started_at', '<=', $filters['date_to'])
            )
            ->orderByDesc('started_at');
    }

    /**
     * Only the keys the filter form owns. Anything else in the query
     * string — a stray page number, something hand-typed — is dropped
     * rather than passed through to a builder.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function filtersFrom(array $input): array
    {
        $filters = array_intersect_key($input, array_flip([
            'converted', 'source', 'device', 'date_from', 'date_to',
        ]));

        // The two enums are whitelisted rather than passed through: both
        // reach a query builder.
        if (! in_array($filters['converted'] ?? null, ['converted', 'not_converted'], true)) {
            unset($filters['converted']);
        }

        if (! in_array($filters['device'] ?? null, [
            UserAgentParser::DEVICE_MOBILE,
            UserAgentParser::DEVICE_TABLET,
            UserAgentParser::DEVICE_DESKTOP,
        ], true)) {
            unset($filters['device']);
        }

        /*
         * The dates reach whereDate(), so anything that is not a REAL
         * calendar date is dropped rather than handed to the driver.
         *
         * Strict, not a shape match: "2026-13-45" passes a regex and is
         * not a date, and it used to reach the query and silently return
         * an empty list. See CalendarDate.
         */
        foreach (['date_from', 'date_to'] as $key) {
            if (! CalendarDate::isValid($filters[$key] ?? null)) {
                unset($filters[$key]);
            }
        }

        if (! is_string($filters['source'] ?? null)) {
            unset($filters['source']);
        }

        return array_filter($filters, fn ($value) => filled($value));
    }

    /**
     * The export payload.
     *
     * A stable, documented shape rather than a model dump: an export is an
     * interface, and toArray() would silently change whenever a column
     * did. Nested by concern so a reader can find what they need without
     * knowing the schema.
     *
     * WHAT IS NOT IN IT. The lead's name, email and phone are deliberately
     * absent — only its id, type and creation time are here. The export is
     * about the JOURNEY; anyone entitled to the contact details already
     * has the Leads section, and a journey file that quietly carried
     * personal contact data is exactly the sort of thing that ends up
     * forwarded outside the business.
     *
     * @param  Collection<int, VisitorSession>  $sessions
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function export(Collection $sessions, array $filters = []): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            // (object) so an empty filter set encodes as {} rather than
            // [], which is what a consumer expecting a map would choke on.
            'filters' => (object) $filters,
            'session_count' => $sessions->count(),
            'sessions' => $sessions->map(fn (VisitorSession $session) => $this->exportSession($session))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportSession(VisitorSession $session): array
    {
        return [
            'id' => $session->id,
            'session_uid' => $session->session_uid,
            'started_at' => $session->started_at?->toIso8601String(),
            'last_activity_at' => $session->last_activity_at?->toIso8601String(),
            'total_duration_seconds' => $session->total_duration_seconds,
            'entry_url' => $session->entry_url,
            'referrer' => $session->referrer,
            'page_count' => $session->page_count,
            // Stated explicitly so a consumer never mistakes a capped
            // journey for a complete one — see VisitorSession::MAX_EVENTS.
            'journey_truncated' => $session->isTruncated(),
            'journey_event_limit' => VisitorSession::MAX_EVENTS,
            'device' => [
                'type' => $session->device_type,
                'browser' => $session->browser,
                'platform' => $session->platform,
            ],
            'location' => [
                // Named ip_prefix, not ip: it is a /24 or a /48, never an
                // address, and the key should say so wherever it is read.
                'ip_prefix' => $session->ip_prefix,
                'country' => $session->country,
            ],
            'attribution' => $session->attribution,
            'converted' => $session->hasConverted(),
            'lead' => $session->lead ? [
                'id' => $session->lead->id,
                'type' => $session->lead->type,
                'created_at' => $session->lead->created_at?->toIso8601String(),
            ] : null,
            'journey' => $session->journey(),
        ];
    }

    /**
     * A file name that sorts chronologically and says what it holds.
     */
    public function exportFilename(?VisitorSession $session = null): string
    {
        $stamp = now()->format('Y-m-d-His');

        return $session
            ? "visitor-session-{$session->id}-{$stamp}.json"
            : "visitor-sessions-{$stamp}.json";
    }
}
