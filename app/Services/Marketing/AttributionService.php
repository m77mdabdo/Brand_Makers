<?php

namespace App\Services\Marketing;

use App\Models\Campaign;
use App\Models\LeadSource;
use Illuminate\Http\Request;

/**
 * Turns the two touch snapshots a public form submits into the full set
 * of Lead attribution attributes.
 *
 * WHICH TOUCH DRIVES THE FLAT COLUMNS. The reporting columns (utm_*,
 * campaign_id, adset_id, ad_id, gclid, fbclid, ttclid, landing_page_url,
 * referrer_url) are populated from the LATEST touch, not the first —
 * latest-touch is what immediately preceded this particular conversion,
 * so it is the honest single answer to "where did THIS lead come from".
 *
 * Both snapshots are stored verbatim in first_touch/latest_touch
 * regardless, so nothing is lost: first-touch is still there for anyone
 * who wants to attribute differently later, it just is not what drives
 * the flat columns today.
 */
class AttributionService
{
    /**
     * Values longer than this are truncated. The columns are varchar(255)
     * and the payload is client-supplied, so a long value must be cut
     * here rather than blowing up the insert.
     */
    private const MAX_LENGTH = 255;

    private ?Campaign $resolvedCampaign = null;

    private bool $campaignResolved = false;

    /**
     * @return array<string, mixed>
     */
    public function resolve(Request $request): array
    {
        $firstTouch = $this->decodeSnapshot($request->input('first_touch_snapshot'));
        $latestTouch = $this->decodeSnapshot($request->input('latest_touch_snapshot'));

        $campaign = $this->resolveCampaign($latestTouch);

        return [
            // Stored verbatim (after sanitising shape and length) — the
            // raw record of what the client reported.
            'first_touch' => $firstTouch,
            'latest_touch' => $latestTouch,

            'source_platform' => $this->resolveSourcePlatform($this->value($latestTouch, 'utm_source')),

            'utm_source' => $this->value($latestTouch, 'utm_source'),
            'utm_medium' => $this->value($latestTouch, 'utm_medium'),
            'utm_campaign' => $this->value($latestTouch, 'utm_campaign'),
            'utm_content' => $this->value($latestTouch, 'utm_content'),
            'utm_term' => $this->value($latestTouch, 'utm_term'),

            /*
             * TWO CAMPAIGN FIELDS, and they are not interchangeable.
             *
             * campaign_id keeps the RAW external string exactly as the ad
             * platform sent it — never rewritten, never replaced by an
             * internal id. It is the source of truth for "what did the
             * platform tell us", and a lead can legitimately carry an id
             * for a campaign nobody has created a record for yet.
             *
             * linked_campaign_id is the resolved internal FK, set only
             * when that external id matches a Campaign. Reporting joins
             * on it; the raw string stays untouched either way.
             */
            'campaign_id' => $this->value($latestTouch, 'campaign_id'),
            'linked_campaign_id' => $campaign?->getKey(),
            'campaign_name' => $campaign?->name,

            'adset_id' => $this->value($latestTouch, 'adset_id'),
            'ad_id' => $this->value($latestTouch, 'ad_id'),

            'gclid' => $this->value($latestTouch, 'gclid'),
            'fbclid' => $this->value($latestTouch, 'fbclid'),
            'ttclid' => $this->value($latestTouch, 'ttclid'),

            'landing_page_url' => $this->value($latestTouch, 'landing_page'),
            'referrer_url' => $this->value($latestTouch, 'referrer'),
        ];
    }

    /**
     * The snapshot arrives as a JSON string in a hidden field, which
     * means it is fully client-controlled. Anything that is not a flat
     * array of scalars is discarded rather than stored.
     *
     * @return array<string, string>|null
     */
    private function decodeSnapshot(mixed $json): ?array
    {
        if (! is_string($json) || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        $clean = [];

        foreach ($decoded as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            $clean[mb_substr($key, 0, 64)] = mb_substr((string) $value, 0, self::MAX_LENGTH);
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * @param  array<string, string>|null  $touch
     */
    private function value(?array $touch, string $key): ?string
    {
        $value = $touch[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, self::MAX_LENGTH);
    }

    /**
     * Matches utm_source against lead_sources.key CASE-INSENSITIVELY, and
     * returns the canonical stored key so "Instagram", "INSTAGRAM" and
     * "instagram" all report as one channel rather than three.
     *
     * Falls back to the raw value when nothing matches — an unrecognised
     * platform is still useful data, and silently dropping it would make
     * the lead look like it came from nowhere. That is also why
     * leads.source_platform is a plain string and not a foreign key.
     *
     * Compared in PHP rather than with a SQL LOWER(): `key` is a reserved
     * word in some dialects, the table is a dozen rows, and this way the
     * comparison is identical on every engine.
     */
    private function resolveSourcePlatform(?string $utmSource): ?string
    {
        if ($utmSource === null) {
            return null;
        }

        $match = LeadSource::all()->first(
            fn (LeadSource $source) => mb_strtolower($source->key) === mb_strtolower($utmSource)
        );

        return $match?->key ?? $utmSource;
    }

    /**
     * Resolves the incoming EXTERNAL campaign id to an internal Campaign,
     * so reporting can join an indexed FK instead of matching free text.
     *
     * Tries campaign_id first, then utm_campaign — some platforms only
     * pass the campaign name through the URL. No match simply means no
     * link.
     *
     * Memoised because resolve() asks for both the key and the name.
     *
     * @param  array<string, string>|null  $latestTouch
     */
    private function resolveCampaign(?array $latestTouch): ?Campaign
    {
        if ($this->campaignResolved) {
            return $this->resolvedCampaign;
        }

        $this->campaignResolved = true;

        $candidates = array_filter([
            $this->value($latestTouch, 'campaign_id'),
            $this->value($latestTouch, 'utm_campaign'),
        ]);

        foreach ($candidates as $candidate) {
            $match = Campaign::where('external_campaign_id', $candidate)->first();

            if ($match) {
                return $this->resolvedCampaign = $match;
            }
        }

        return $this->resolvedCampaign = null;
    }
}
