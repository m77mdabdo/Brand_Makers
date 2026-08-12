@props([
    'name',
    'label',
    'type' => 'text',
    'model' => null,
    'required' => true,
    'help' => null,
    'rows' => 4,
])

@php
    /*
     * The bilingual pair, rendered from one call.
     *
     * Arabic is marked required and English is not — the project-wide
     * convention. Arabic is the default locale, so a record with only
     * English would render as a gap on the primary site, whereas a record
     * with only Arabic falls back correctly everywhere.
     *
     * Values are read with getTranslation(..., false) — WITHOUT the
     * fallback — because this is an editor: showing the Arabic text
     * inside the English box would make an admin think a translation
     * exists, and saving would then duplicate it.
     */
    /*
     * The form name may be DOTTED (seo.meta_title) while the model
     * attribute is just the last segment (meta_title) — the SEO fields
     * post into a nested `seo` array but read off a SeoMeta record.
     * Passing the dotted name straight to getTranslation() throws
     * "not one of the translatable attributes".
     */
    $attribute = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name;

    $stored = fn (string $locale) => $model && in_array($attribute, $model->translatable ?? [], true)
        ? ($model->getTranslation($attribute, $locale, false) ?: '')
        : '';
@endphp

<fieldset>
    <legend class="block text-sm font-bold text-ink">
        {{ $label }}
        @if ($required)
            <span class="text-orange-ink" aria-hidden="true">*</span>
        @endif
    </legend>

    @if ($help)
        <p class="mt-1 text-xs text-muted-ink">{{ $help }}</p>
    @endif

    <div class="mt-2 grid gap-3 lg:grid-cols-2">
        @foreach (['ar' => __('dashboard.common.arabic'), 'en' => __('dashboard.common.english')] as $locale => $localeLabel)
            <div>
                <label for="{{ $name }}_{{ $locale }}" class="mb-1 block text-xs font-bold text-muted-ink">
                    {{ $localeLabel }}
                    @if ($locale === 'en')
                        <span class="font-normal">({{ __('dashboard.common.optional') }})</span>
                    @endif
                </label>

                @if ($type === 'textarea' || $type === 'rich')
                    <textarea
                        id="{{ $name }}_{{ $locale }}"
                        name="{{ $name }}[{{ $locale }}]"
                        rows="{{ $rows }}"
                        dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}"
                        @if ($required && $locale === 'ar') required @endif
                        class="block w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol {{ $type === 'rich' ? 'font-mono text-xs' : '' }}"
                    >{{ old("{$name}.{$locale}", $stored($locale)) }}</textarea>
                @else
                    <input
                        id="{{ $name }}_{{ $locale }}"
                        name="{{ $name }}[{{ $locale }}]"
                        type="text"
                        dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}"
                        value="{{ old("{$name}.{$locale}", $stored($locale)) }}"
                        @if ($required && $locale === 'ar') required @endif
                        class="block w-full min-h-[44px] rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                    >
                @endif

                <x-input-error :messages="$errors->get(\"{$name}.{$locale}\")" class="mt-1" />
            </div>
        @endforeach
    </div>

    @if ($type === 'rich')
        <p class="mt-1 text-xs text-muted-ink">{{ __('dashboard.common.rich_text_help') }}</p>
    @endif
</fieldset>
