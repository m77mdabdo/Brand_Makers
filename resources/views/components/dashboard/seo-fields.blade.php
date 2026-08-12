@props(['model' => null])

@php
    // The morph relation may not exist yet — a record with no SeoMeta row
    // is the normal state, since the service refuses to create a blank one.
    $seo = $model?->seoMeta;
@endphp

<x-dashboard.card :title="__('dashboard.seo.heading')" :subtitle="__('dashboard.seo.intro')">
    <x-dashboard.translatable-field
        name="seo.meta_title"
        :label="__('dashboard.seo.meta_title')"
        :model="$seo"
        :required="false"
    />

    <x-dashboard.translatable-field
        name="seo.meta_description"
        :label="__('dashboard.seo.meta_description')"
        :model="$seo"
        type="textarea"
        :rows="3"
        :required="false"
    />

    <x-dashboard.field
        name="seo.canonical_url"
        :label="__('dashboard.seo.canonical_url')"
        type="url"
        dir="ltr"
        :value="$seo?->canonical_url"
        :help="__('dashboard.seo.canonical_help')"
    />

    <div>
        <x-dashboard.field name="seo_og_image" :label="__('dashboard.seo.og_image')" type="file" :help="__('dashboard.seo.og_image_help')" />

        @if ($seo?->og_image)
            <img src="{{ Storage::url($seo->og_image) }}" alt="" class="mt-2 h-20 w-auto rounded-lg border border-line">
        @endif
    </div>
</x-dashboard.card>
