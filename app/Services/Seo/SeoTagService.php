<?php

namespace App\Services\Seo;

use App\Services\Cms\SettingsRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Resolves the <head> metadata for a page through one explicit fallback
 * chain, so no page can silently ship with a generic title.
 *
 * The order, most specific first:
 *   1. the record's own SeoMeta row (admin-authored, per-locale)
 *   2. whatever the view passed in
 *   3. the record's own content (title/name, summary/excerpt)
 *   4. the site-wide seo_defaults settings
 *
 * Per-locale values are read WITHOUT a translation fallback at step 1 and
 * 3 — an Arabic string leaking into an English <title> is worse than
 * falling through to the site default, because search engines index it.
 */
class SeoTagService
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function title(?Model $model = null, ?string $fallback = null): string
    {
        $title = $this->fromSeoMeta($model, 'meta_title')
            ?? $fallback
            ?? $this->fromContent($model, ['title', 'name'])
            ?? $this->settings->translated('default_meta_title')
            ?? __('site.brand.name');

        $brand = $this->settings->translated('site_name') ?? __('site.brand.name');

        // Suffix the brand unless the resolved title already carries it —
        // otherwise an admin-authored meta_title that ends in the brand
        // name would render it twice.
        if (! Str::contains($title, $brand)) {
            $title = $title.' — '.$brand;
        }

        return Str::limit($title, 120, '');
    }

    public function description(?Model $model = null, ?string $fallback = null): ?string
    {
        $description = $this->fromSeoMeta($model, 'meta_description')
            ?? $fallback
            ?? $this->fromContent($model, ['excerpt', 'summary', 'description'])
            ?? $this->settings->translated('default_meta_description');

        if ($description === null) {
            return null;
        }

        return Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($description)) ?? ''), 160, '');
    }

    /**
     * The absolute URL for this exact page.
     *
     * Query strings are stripped deliberately: every UTM-tagged variant of
     * a landing page is the same document, and letting them each declare
     * themselves canonical is how attribution parameters end up
     * fragmenting a page's search ranking.
     */
    public function canonical(): string
    {
        return url()->current();
    }

    public function image(?Model $model = null): string
    {
        $ogImage = $this->fromSeoMeta($model, 'og_image', translated: false);

        return $ogImage
            ? asset($ogImage)
            : asset('og-image.jpg');
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function fromContent(?Model $model, array $fields): ?string
    {
        if (! $model) {
            return null;
        }

        foreach ($fields as $field) {
            $value = $this->translatedAttribute($model, $field);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function fromSeoMeta(?Model $model, string $field, bool $translated = true): ?string
    {
        if (! $model || ! method_exists($model, 'seoMeta')) {
            return null;
        }

        $meta = $model->relationLoaded('seoMeta') ? $model->seoMeta : $model->seoMeta()->first();

        if (! $meta) {
            return null;
        }

        if (! $translated) {
            $value = $meta->getAttribute($field);

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return $this->translatedAttribute($meta, $field);
    }

    /**
     * A model's per-locale value with NO fallback to another language.
     * Models that are not translatable fall back to the raw attribute.
     */
    private function translatedAttribute(Model $model, string $field): ?string
    {
        if (! in_array($field, $model->translatable ?? [], true)) {
            $value = $model->getAttribute($field);

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        $value = $model->getTranslation($field, app()->getLocale(), false);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
