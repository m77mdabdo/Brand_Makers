<?php

namespace App\Http\Requests\Concerns;

/**
 * Shared SEO-meta validation for every seoMetable resource (Service,
 * Country, Article, Page, Package), so the rules live in one place rather
 * than being repeated across ten Store/Update request classes.
 *
 * Unlike the content fields, `.ar` is NOT unconditionally required here:
 * SEO meta is supplemental and an admin must be able to save a record
 * without filling it in — the public side falls back to the record's own
 * content. What IS enforced is the project's Arabic-primary convention:
 * an English value may not be supplied without its Arabic counterpart,
 * because that would render as a gap on the default locale.
 */
trait HasSeoMetaRules
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function seoMetaRules(): array
    {
        return [
            'seo' => ['nullable', 'array'],

            'seo.meta_title' => ['nullable', 'array'],
            'seo.meta_title.ar' => ['nullable', 'required_with:seo.meta_title.en', 'string', 'max:255'],
            'seo.meta_title.en' => ['nullable', 'string', 'max:255'],

            'seo.meta_description' => ['nullable', 'array'],
            'seo.meta_description.ar' => ['nullable', 'required_with:seo.meta_description.en', 'string', 'max:320'],
            'seo.meta_description.en' => ['nullable', 'string', 'max:320'],

            'seo.canonical_url' => ['nullable', 'url', 'max:2048'],

            // No SVG: it is an executable document, storage/ is served
            // same-origin, and there is no sanitiser for it in this
            // project.
            'seo_og_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
