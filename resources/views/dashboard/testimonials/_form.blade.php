@props(['testimonial' => null])

<x-dashboard.card>
    <x-dashboard.translatable-field name="quote" :label="__('dashboard.fields.quote')" :model="$testimonial" type="textarea" :rows="4" />

    <div class="grid gap-5 sm:grid-cols-3">
        <x-dashboard.field name="client_name" :label="__('dashboard.fields.client_name')" :value="$testimonial?->client_name" :required="true" />
        <x-dashboard.field name="client_title" :label="__('dashboard.fields.client_title')" :value="$testimonial?->client_title" />
        <x-dashboard.field name="client_country" :label="__('dashboard.fields.client_country')" :value="$testimonial?->client_country" />
    </div>
</x-dashboard.card>

<x-dashboard.card>
    <x-dashboard.field name="sort_order" :label="__('dashboard.fields.sort_order')" type="number" :value="$testimonial?->sort_order ?? 0" />

    <div>
        <x-dashboard.field name="avatar" :label="__('dashboard.fields.avatar')" type="file" />
        @if ($testimonial?->avatar)
            <img src="{{ Storage::url($testimonial->avatar) }}" alt="" class="mt-2 h-14 w-14 rounded-full border border-line object-cover">
        @endif
    </div>

    <x-dashboard.checkbox name="is_active" :label="__('dashboard.fields.is_active')" :checked="$testimonial?->is_active ?? true" />
</x-dashboard.card>
