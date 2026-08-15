<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreCampaignRequest;
use App\Http\Requests\Dashboard\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Services\Marketing\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The campaign register.
 *
 * WHAT THIS TURNS ON. AttributionService already resolves a lead's
 * campaign by matching the ad platform's `campaign_id` against
 * `campaigns.external_campaign_id`, and that half has been tested since
 * the attribution build — but nothing could create a campaign, so the
 * table was empty in production and `linked_campaign_id` was always null.
 * The raw string was captured, the join never resolved, and the "Linked
 * campaign" panel on every lead read blank.
 *
 * Saving a campaign here also back-links the enquiries that arrived before
 * it existed — see CampaignService::relinkLeads().
 */
class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns) {}

    public function index(): View
    {
        return view('dashboard.campaigns.index', [
            /*
             * withCount on the RESOLVED relation (linked_campaign_id), not
             * on the raw string column — that count is the whole point of
             * the screen, since it is what tells an admin whether their
             * external id actually matches what the ad platform sends.
             */
            'campaigns' => Campaign::query()->withCount('leads')->orderByDesc('is_active')->orderBy('name')->paginate(25),
            'platforms' => Campaign::PLATFORMS,
        ]);
    }

    public function create(): View
    {
        return view('dashboard.campaigns.create', ['platforms' => Campaign::PLATFORMS]);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $campaign = $this->campaigns->create($request->validated());

        return redirect()
            ->route('dashboard.campaigns.index')
            ->with('status', $this->savedMessage($campaign->leads()->count()));
    }

    public function edit(Campaign $campaign): View
    {
        return view('dashboard.campaigns.edit', [
            'campaign' => $campaign,
            'platforms' => Campaign::PLATFORMS,
        ]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->campaigns->update($campaign, $request->validated());

        return redirect()
            ->route('dashboard.campaigns.index')
            ->with('status', $this->savedMessage($campaign->leads()->count()));
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->campaigns->delete($campaign);

        return redirect()
            ->route('dashboard.campaigns.index')
            ->with('status', __('dashboard.flash.deleted'));
    }

    /**
     * Reports how many enquiries the campaign now owns.
     *
     * Worth saying out loud rather than a bare "Saved": back-linking is
     * invisible otherwise, and the count is the fastest way for an admin
     * to tell whether the external id they typed actually matches what the
     * ad platform has been sending.
     */
    private function savedMessage(int $linked): string
    {
        return $linked > 0
            ? trans_choice('dashboard.campaigns.saved_with_leads', $linked, ['count' => $linked])
            : __('dashboard.flash.updated');
    }
}
