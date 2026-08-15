@props(['campaign' => null, 'platforms' => []])

<x-dashboard.card>
    <div class="grid gap-5 sm:grid-cols-2">
        <x-dashboard.field name="name" :label="__('dashboard.campaigns.name')" :value="$campaign?->name" required />

        <x-dashboard.field
            name="platform"
            :label="__('dashboard.campaigns.platform')"
            :value="$campaign?->platform"
            :options="['' => __('dashboard.common.none')] + collect($platforms)->mapWithKeys(fn ($p) => [$p => __('dashboard.campaigns.platforms.'.$p)])->all()"
        />
    </div>

    {{-- The join key. Everything this screen exists for hangs off it, so
         it gets its own row and an explanation rather than sitting in a
         grid with the budget. --}}
    <x-dashboard.field
        name="external_campaign_id"
        dir="ltr"
        :label="__('dashboard.campaigns.external_id')"
        :value="$campaign?->external_campaign_id"
        :help="__('dashboard.campaigns.external_id_help')"
    />
</x-dashboard.card>

<x-dashboard.card :title="__('dashboard.campaigns.spend_heading')">
    <div class="grid gap-5 sm:grid-cols-3">
        <x-dashboard.field name="budget" type="number" dir="ltr" :label="__('dashboard.campaigns.budget')" :value="$campaign?->budget" />
        <x-dashboard.field name="spend" type="number" dir="ltr" :label="__('dashboard.campaigns.spend')" :value="$campaign?->spend" />
        <x-dashboard.field name="currency" dir="ltr" :label="__('dashboard.campaigns.currency')" :value="$campaign?->currency ?? 'SAR'" />
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <x-dashboard.field name="starts_on" type="date" dir="ltr" :label="__('dashboard.campaigns.starts_on')" :value="$campaign?->starts_on?->toDateString()" />
        <x-dashboard.field name="ends_on" type="date" dir="ltr" :label="__('dashboard.campaigns.ends_on')" :value="$campaign?->ends_on?->toDateString()" />
    </div>

    <x-dashboard.field name="notes" type="textarea" :label="__('dashboard.campaigns.notes')" :value="$campaign?->notes" />

    <x-dashboard.checkbox name="is_active" :label="__('dashboard.fields.is_active')" :checked="$campaign?->is_active ?? true" />
</x-dashboard.card>
