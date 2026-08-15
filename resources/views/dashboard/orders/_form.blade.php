@props(['order' => null, 'packages' => [], 'leads' => []])

<x-dashboard.card>
    <div class="grid gap-5 sm:grid-cols-2">
        <x-dashboard.field name="client_name" :label="__('dashboard.orders.client_name')" :value="$order?->client_name" required />
        <x-dashboard.field name="client_email" type="email" dir="ltr" :label="__('dashboard.orders.client_email')" :value="$order?->client_email" />
        <x-dashboard.field name="client_phone" type="tel" dir="ltr" :label="__('dashboard.orders.client_phone')" :value="$order?->client_phone" />
        <x-dashboard.field
            name="package_id"
            :label="__('dashboard.resources.packages')"
            :value="$order?->package_id"
            :options="['' => __('dashboard.common.none')] + collect($packages)->mapWithKeys(fn ($p) => [$p->id => $p->name])->all()"
        />
        <x-dashboard.field
            name="lead_id"
            :label="__('dashboard.orders.linked_lead')"
            :value="$order?->lead_id"
            :options="['' => __('dashboard.common.none')] + collect($leads)->mapWithKeys(fn ($l) => [$l->id => $l->full_name.' · '.__('dashboard.lead_types.'.$l->type)])->all()"
        />
        <x-dashboard.field name="total" type="number" dir="ltr" :label="__('dashboard.orders.total')" :value="$order?->total" />
        <x-dashboard.field
            name="placed_at"
            type="date"
            dir="ltr"
            :label="__('dashboard.orders.placed_at')"
            :value="$order?->placed_at?->toDateString()"
        />
    </div>

    <x-dashboard.field
        name="notes"
        type="textarea"
        :label="__('dashboard.orders.notes')"
        :value="$order?->notes"
        :help="__('dashboard.orders.notes_help')"
    />
</x-dashboard.card>

@unless ($order)
    {{-- The opening timeline entry, written in the same transaction as the
         order. An order whose portal shows an empty timeline reads as
         broken, and the client's first question would be whether anything
         was received at all. --}}
    <x-dashboard.card :title="__('dashboard.orders.timeline')">
        <x-dashboard.translatable-field name="note" :label="__('dashboard.orders.note')" :required="false" :rows="3" />
    </x-dashboard.card>
@endunless
