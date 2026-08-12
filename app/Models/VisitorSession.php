<?php

namespace App\Models;

use App\Models\Concerns\AnonymizesIp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One visitor's journey through the public site.
 *
 * Complements attribution rather than duplicating it: `attribution` here
 * is a copy of the same touch cookies the forms submit, kept so a session
 * reads on its own, but `leads` remains the source of truth for where a
 * lead came from.
 */
class VisitorSession extends Model
{
    use AnonymizesIp, HasFactory;

    /**
     * Hard ceiling on the stored journey.
     *
     * `events` is a JSON column on a row updated on every single page
     * view, so it is the one part of this feature that can grow without
     * bound — a bot that slips the filter, or a genuinely long session,
     * would otherwise rewrite an ever-larger blob on every request.
     *
     * Past the cap, appends are DROPPED but `page_count` keeps counting,
     * so a truncated journey is still visibly a long one rather than
     * silently looking like a 100-page visit.
     */
    public const MAX_EVENTS = 100;

    public const TYPE_PAGEVIEW = 'pageview';

    public const TYPE_ACTION = 'action';

    /**
     * Deliberately narrow. Everything here is written from server-side
     * request data, never from a request payload.
     */
    protected $fillable = [
        'session_uid',
        'started_at',
        'last_activity_at',
        'entry_url',
        'referrer',
        'attribution',
        'device_type',
        'browser',
        'platform',
        'ip_prefix',
        'country',
        'events',
        'page_count',
        'total_duration_seconds',
        'lead_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'attribution' => 'array',
            'events' => 'array',
            'page_count' => 'integer',
            'total_duration_seconds' => 'integer',
        ];
    }

    /**
     * The column is named ip_prefix here, not ip_address — the trait
     * anonymizes whatever it is told to, and the name says what is
     * actually stored.
     *
     * @return list<string>
     */
    protected function anonymizedIpAttributes(): array
    {
        return ['ip_prefix'];
    }

    /**
     * withTrashed(): Lead soft-deletes and lead_id is nullOnDelete, so a
     * journey attached to an archived lead keeps pointing at it. A
     * converted session that suddenly reads as unconverted would be a
     * silently wrong analytics number.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function scopeConverted(Builder $query): Builder
    {
        return $query->whereNotNull('lead_id');
    }

    public function scopeNotConverted(Builder $query): Builder
    {
        return $query->whereNull('lead_id');
    }

    public function hasConverted(): bool
    {
        return $this->lead_id !== null;
    }

    /**
     * True when the journey was longer than what is stored — see
     * MAX_EVENTS.
     */
    public function isTruncated(): bool
    {
        return $this->page_count > count($this->events ?? []);
    }

    /**
     * The journey, oldest first, with anything malformed dropped.
     *
     * Defensive because `events` is a JSON blob: a partial write, a manual
     * edit or a future schema change should degrade to a shorter timeline,
     * not a 500 on the detail page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function journey(): array
    {
        return array_values(array_filter(
            $this->events ?? [],
            fn ($event) => is_array($event) && isset($event['type'], $event['ts'])
        ));
    }

    /**
     * The session length, for a person reading a table. Seconds below a
     * minute, m:ss above it — "3:07" is read faster than "187 seconds".
     */
    public function durationForHumans(): string
    {
        $seconds = max(0, $this->total_duration_seconds);

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainder = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $remainder)
            : sprintf('%d:%02d', $minutes, $remainder);
    }

    /**
     * The attribution source this session arrived on. Reads the latest
     * touch first: that is what immediately preceded the visit.
     */
    public function sourcePlatform(): ?string
    {
        return $this->attributionValue('utm_source');
    }

    public function campaignName(): ?string
    {
        return $this->attributionValue('utm_campaign');
    }

    private function attributionValue(string $key): ?string
    {
        $value = $this->attribution['latest_touch'][$key]
            ?? $this->attribution['first_touch'][$key]
            ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
