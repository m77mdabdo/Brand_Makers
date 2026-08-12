<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Country;
use App\Models\Package;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Gives every seeded record a SeoMeta row, so a fresh install has real
 * per-page metadata rather than relying purely on the site-wide fallback.
 *
 * Deliberately DERIVES the values from each record's own content instead
 * of hand-writing another sixty strings: that keeps this seeder in step
 * with the content seeders automatically, and a meta description genuinely
 * should read like a trimmed version of the page it describes.
 *
 * Runs last (see DatabaseSeeder) because it reads what the others created.
 * Idempotent — updateOrCreate keyed on the morph pair, which is also the
 * table's unique index.
 */
class SeoMetaSeeder extends Seeder
{
    private const BRAND_AR = 'صنّاع العلامات التجارية';

    private const BRAND_EN = 'Creative Mark';

    public function run(): void
    {
        Page::all()->each(fn (Page $page) => $this->seedFor(
            $page,
            titleField: 'title',
            descriptionFields: ['meta_description', 'body'],
        ));

        Article::all()->each(fn (Article $article) => $this->seedFor(
            $article,
            titleField: 'title',
            descriptionFields: ['excerpt', 'body'],
        ));

        Service::all()->each(fn (Service $service) => $this->seedFor(
            $service,
            titleField: 'name',
            descriptionFields: ['summary', 'body'],
        ));

        Country::all()->each(fn (Country $country) => $this->seedFor(
            $country,
            titleField: 'name',
            descriptionFields: ['notes'],
        ));

        Package::all()->each(fn (Package $package) => $this->seedFor(
            $package,
            titleField: 'name',
            descriptionFields: ['description'],
        ));
    }

    /**
     * @param  list<string>  $descriptionFields  tried in order; first non-empty wins
     */
    private function seedFor(Model $model, string $titleField, array $descriptionFields): void
    {
        $metaTitle = array_filter([
            'ar' => $this->suffixed($this->text($model, $titleField, 'ar'), self::BRAND_AR),
            'en' => $this->suffixed($this->text($model, $titleField, 'en'), self::BRAND_EN),
        ]);

        $metaDescription = array_filter([
            'ar' => $this->describe($model, $descriptionFields, 'ar'),
            'en' => $this->describe($model, $descriptionFields, 'en'),
        ]);

        if ($metaTitle === [] && $metaDescription === []) {
            return;
        }

        SeoMeta::updateOrCreate(
            [
                // The morph ALIAS — enforceMorphMap() means anything else
                // would throw rather than write an unresolvable row.
                'seo_metable_type' => $model->getMorphClass(),
                'seo_metable_id' => $model->getKey(),
            ],
            [
                'meta_title' => $metaTitle ?: null,
                'meta_description' => $metaDescription ?: null,
            ]
        );
    }

    private function suffixed(?string $title, string $brand): ?string
    {
        return $title ? Str::limit($title.' — '.$brand, 120, '') : null;
    }

    /**
     * @param  list<string>  $fields
     */
    private function describe(Model $model, array $fields, string $locale): ?string
    {
        foreach ($fields as $field) {
            $value = $this->text($model, $field, $locale);

            if ($value === null) {
                continue;
            }

            $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

            if ($plain !== '') {
                return Str::limit($plain, 160, '');
            }
        }

        return null;
    }

    /**
     * Raw per-locale value with NO fallback — an Arabic string must never
     * leak into the English meta tag, which is exactly what a fallback
     * would do here.
     */
    private function text(Model $model, string $field, string $locale): ?string
    {
        $value = $model->getTranslation($field, $locale, false);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
