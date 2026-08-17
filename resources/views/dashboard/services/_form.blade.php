@props(['service' => null])

<x-dashboard.card>
    <x-dashboard.translatable-field name="name" :label="__('dashboard.fields.name')" :model="$service" />
    <x-dashboard.translatable-field name="summary" :label="__('dashboard.fields.summary')" :model="$service" type="textarea" :rows="3" />
    <x-dashboard.translatable-field name="body" :label="__('dashboard.fields.body')" :model="$service" type="rich" :rows="8" />
    <x-dashboard.translatable-field name="requirements" :label="__('dashboard.fields.requirements')" :model="$service" type="rich" :rows="4" />
    <x-dashboard.translatable-field name="process" :label="__('dashboard.fields.process')" :model="$service" type="rich" :rows="4" />
</x-dashboard.card>

<x-dashboard.card>
    <div class="grid gap-5 sm:grid-cols-2">
        <x-dashboard.field name="slug" :label="__('dashboard.fields.slug')" dir="ltr" :value="$service?->slug" :help="__('dashboard.fields.slug_help')" />
        <x-dashboard.field name="icon" :label="__('dashboard.fields.icon')" dir="ltr" :value="$service?->icon" />
        <x-dashboard.field name="sort_order" :label="__('dashboard.fields.sort_order')" type="number" :value="$service?->sort_order ?? 0" />

        {{-- The single source of truth for what this service costs: the
             services page and the estimator both read this column, so a
             figure typed here reaches both without a code change. Leave it
             blank for "على الطلب". --}}
        <x-dashboard.field
            name="price"
            type="number"
            step="0.01"
            dir="ltr"
            :label="__('dashboard.services.price')"
            :value="$service?->price"
            :help="__('dashboard.services.price_help')"
        />
    </div>

    <div>
        <x-dashboard.field name="cover_image" :label="__('dashboard.fields.cover_image')" type="file" />
        @if ($service?->cover_image)
            <img src="{{ Storage::url($service->cover_image) }}" alt="" class="mt-2 h-20 w-auto rounded-lg border border-line">
        @endif
    </div>

    <div class="flex flex-wrap gap-6">
        <x-dashboard.checkbox name="is_active" :label="__('dashboard.fields.is_active')" :checked="$service?->is_active ?? true" />
        <x-dashboard.checkbox name="is_flagship" :label="__('dashboard.fields.is_flagship')" :checked="$service?->is_flagship ?? false" />
    </div>
</x-dashboard.card>

<x-dashboard.seo-fields :model="$service" />
