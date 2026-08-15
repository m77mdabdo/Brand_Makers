@props(['package' => null])

@php
    /*
     * The stored list is [{"ar": "…", "en": "…"}, …]; the editor shows it
     * as two textareas, one line per feature. Rebuilt here in the same
     * order it is stored, so what an admin sees is exactly what will be
     * saved back — see PackageContentService::featureList().
     */
    $features = collect($package?->features ?? []);
    $featuresAr = $features->map(fn ($f) => is_array($f) ? ($f['ar'] ?? '') : (string) $f)->implode("\n");
    $featuresEn = $features->map(fn ($f) => is_array($f) ? ($f['en'] ?? '') : '')->implode("\n");
@endphp

<x-dashboard.card>
    <x-dashboard.translatable-field name="name" :label="__('dashboard.fields.name')" :model="$package" />
    <x-dashboard.translatable-field
        name="description"
        :label="__('dashboard.fields.description')"
        :model="$package"
        type="textarea"
        :rows="3"
        :required="false"
    />
</x-dashboard.card>

<x-dashboard.card :title="__('dashboard.packages.pricing')" :subtitle="__('dashboard.packages.price_help')">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-dashboard.field
            name="price"
            type="number"
            dir="ltr"
            :label="__('dashboard.packages.price')"
            :value="$package?->price"
            :help="__('dashboard.packages.price_blank_help')"
        />
        <x-dashboard.field
            name="currency"
            dir="ltr"
            :label="__('dashboard.packages.currency')"
            :value="$package?->currency ?? 'SAR'"
        />
    </div>
</x-dashboard.card>

<x-dashboard.card :title="__('dashboard.packages.features')" :subtitle="__('dashboard.packages.features_help')">
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="features_ar" class="block text-sm font-bold text-ink">
                {{ __('dashboard.common.arabic') }}
                <span class="text-orange-ink" aria-hidden="true">*</span>
            </label>
            <textarea
                id="features_ar"
                name="features[ar]"
                rows="8"
                dir="rtl"
                class="mt-1 block w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
            >{{ old('features.ar', $featuresAr) }}</textarea>
            <x-input-error :messages="$errors->get('features.ar')" class="mt-1" />
        </div>

        <div>
            <label for="features_en" class="block text-sm font-bold text-ink">{{ __('dashboard.common.english') }}</label>
            <textarea
                id="features_en"
                name="features[en]"
                rows="8"
                dir="ltr"
                class="mt-1 block w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
            >{{ old('features.en', $featuresEn) }}</textarea>
            <x-input-error :messages="$errors->get('features.en')" class="mt-1" />
        </div>
    </div>
</x-dashboard.card>

<x-dashboard.card>
    <div class="grid gap-5 sm:grid-cols-2">
        <x-dashboard.field name="slug" dir="ltr" :label="__('dashboard.fields.slug')" :value="$package?->slug" :help="__('dashboard.fields.slug_help')" />
        <x-dashboard.field name="sort_order" type="number" :label="__('dashboard.fields.sort_order')" :value="$package?->sort_order ?? 0" />
    </div>

    <div class="flex flex-wrap gap-6">
        <x-dashboard.checkbox name="is_active" :label="__('dashboard.fields.is_active')" :checked="$package?->is_active ?? true" />
        <x-dashboard.checkbox
            name="is_popular"
            :label="__('dashboard.packages.is_popular')"
            :checked="$package?->is_popular ?? false"
            :help="__('dashboard.packages.is_popular_help')"
        />
    </div>
</x-dashboard.card>

<x-dashboard.seo-fields :model="$package" />
