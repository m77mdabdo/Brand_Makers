<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    /**
     * The route already carries ['auth', 'admin'], so authorisation here
     * is a deliberate pass-through rather than an omission.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A campaign is internal marketing configuration, not site content —
     * so `name` is a plain string rather than a translatable pair. It is
     * never rendered on the public site.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // A fixed vocabulary, because reporting groups on these values.
            'platform' => ['nullable', 'string', Rule::in(Campaign::PLATFORMS)],

            /*
             * The join key. Unique because AttributionService resolves a
             * lead by matching this against the ad platform's campaign_id,
             * and two campaigns sharing one id would make that match
             * ambiguous — whichever row happened to come back first would
             * silently own the lead.
             *
             * ->ignore() the row being edited, or saving a campaign
             * without touching its external id would report the id as
             * already taken by itself.
             */
            'external_campaign_id' => ['nullable', 'string', 'max:255', Rule::unique('campaigns', 'external_campaign_id')->ignore($this->route('campaign'))],

            'budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'spend' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],

            'starts_on' => ['nullable', 'date'],
            // after_or_equal, not after: a one-day campaign starts and ends
            // on the same date.
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],

            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('dashboard.campaigns.name'),
            'platform' => __('dashboard.campaigns.platform'),
            'external_campaign_id' => __('dashboard.campaigns.external_id'),
            'budget' => __('dashboard.campaigns.budget'),
            'spend' => __('dashboard.campaigns.spend'),
            'starts_on' => __('dashboard.campaigns.starts_on'),
            'ends_on' => __('dashboard.campaigns.ends_on'),
        ];
    }
}
