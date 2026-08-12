@props(['country' => null])

<x-dashboard.card>
    <x-dashboard.translatable-field name="name" :label="__('dashboard.fields.name')" :model="$country" />
    <x-dashboard.translatable-field name="notes" :label="__('dashboard.fields.notes')" :model="$country" type="rich" :rows="5" :required="false" />
</x-dashboard.card>

<x-dashboard.card>
    <div class="grid gap-5 sm:grid-cols-2">
        <x-dashboard.field name="slug" :label="__('dashboard.fields.slug')" dir="ltr" :value="$country?->slug" :help="__('dashboard.fields.slug_help')" />
        <x-dashboard.field name="sort_order" :label="__('dashboard.fields.sort_order')" type="number" :value="$country?->sort_order ?? 0" />
    </div>

    <div>
        <x-dashboard.field name="flag_image" :label="__('dashboard.fields.flag_image')" type="file" />
        @if ($country?->flag_image)
            <img src="{{ Storage::url($country->flag_image) }}" alt="" class="mt-2 h-14 w-auto rounded-lg border border-line">
        @endif
    </div>

    <x-dashboard.checkbox name="is_active" :label="__('dashboard.fields.is_active')" :checked="$country?->is_active ?? true" />
</x-dashboard.card>

<x-dashboard.seo-fields :model="$country" />
