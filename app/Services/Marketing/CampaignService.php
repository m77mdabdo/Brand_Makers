<?php

namespace App\Services\Marketing;

use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

/**
 * Writes to the campaign register, and reconciles the leads that were
 * already waiting for it.
 *
 * WHY BACK-LINKING EXISTS. AttributionService resolves a lead's campaign
 * at submission time by matching the ad platform's `campaign_id` (or
 * `utm_campaign`) against `campaigns.external_campaign_id`. If the
 * campaign row does not exist yet — which it never did before this screen,
 * and will routinely not when a new ad goes live before anyone records it
 * — the lead keeps the raw string and `linked_campaign_id` stays null.
 *
 * That data is not lost, but the join is missing, so the campaign's leads
 * list reads empty while the enquiries sit there carrying its id. Creating
 * the campaign afterwards is exactly the moment to reconcile, so
 * relinkLeads() runs on every save.
 */
class CampaignService
{
    /**
     * The only columns this service will pass to a mass assignment.
     *
     * A second layer behind the Form Request, matching the arrangement in
     * OrderService and EstimatorItemService: a key that survived
     * validation is still never read.
     *
     * @var list<string>
     */
    private const WRITABLE = [
        'name', 'platform', 'external_campaign_id',
        'budget', 'spend', 'currency',
        'starts_on', 'ends_on', 'is_active', 'notes',
    ];

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function create(array $data): Campaign
    {
        return DB::transaction(function () use ($data) {
            $campaign = Campaign::create($this->writable($data));

            $this->relinkLeads($campaign);

            return $campaign;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        return DB::transaction(function () use ($campaign, $data) {
            $campaign->update($this->writable($data));

            // Re-run after an edit too: the commonest edit is correcting a
            // mistyped external id, and that is precisely the change that
            // should pick up the leads it was meant to match.
            $this->relinkLeads($campaign);

            return $campaign->refresh();
        });
    }

    public function delete(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            /*
             * Campaigns do not soft-delete, and leads.linked_campaign_id
             * is nullOnDelete — so the FK would clear itself. Doing it
             * explicitly here keeps the intent visible: the LEAD survives
             * and keeps its raw campaign_id string, so deleting a campaign
             * loses the join and never the attribution.
             */
            Lead::where('linked_campaign_id', $campaign->getKey())->update(['linked_campaign_id' => null]);

            $campaign->delete();
        });
    }

    /**
     * Attaches every unlinked lead whose recorded campaign identifier
     * matches this campaign's external id.
     *
     * TWO COLUMNS ARE MATCHED, mirroring AttributionService::resolveCampaign()
     * exactly — `campaign_id` (what an ad platform passes) first, then
     * `utm_campaign` (what a hand-built link usually carries). If those
     * two ever disagree about what counts as a match, attribution at
     * submission time and reconciliation afterwards would produce
     * different answers for the same lead.
     *
     * ONLY NULLS ARE FILLED. A lead already pointing at another campaign
     * is left alone: silently repointing somebody else's attribution
     * because two campaigns share an id string would corrupt the reporting
     * this feature exists to feed.
     *
     * @return int the number of leads newly linked
     */
    public function relinkLeads(Campaign $campaign): int
    {
        $external = trim((string) $campaign->external_campaign_id);

        // No external id means nothing to match on — every lead would
        // qualify against an empty string.
        if ($external === '') {
            return 0;
        }

        return Lead::query()
            ->whereNull('linked_campaign_id')
            ->where(fn ($query) => $query
                ->where('campaign_id', $external)
                ->orWhere('utm_campaign', $external))
            ->update(['linked_campaign_id' => $campaign->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function writable(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::WRITABLE));

        // An unticked checkbox is absent from the payload entirely, so
        // "not submitted" IS false here rather than "leave alone".
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        foreach (['budget', 'spend'] as $money) {
            if (array_key_exists($money, $data)) {
                $data[$money] = $this->amount($data[$money]);
            }
        }

        // Blank is null, not zero: "no budget recorded" and "a budget of
        // nothing" are different, and the spend-vs-budget column would
        // read as 100% overspent on the second.
        foreach (['external_campaign_id', 'platform', 'starts_on', 'ends_on', 'notes'] as $nullable) {
            if (array_key_exists($nullable, $data) && $data[$nullable] === '') {
                $data[$nullable] = null;
            }
        }

        return $data;
    }

    private function amount(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }
}
