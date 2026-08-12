<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * A pricing package.
 *
 * `features` is a plain JSON array of per-locale maps rather than a spatie
 * translatable column: translatable columns hold ONE value per locale, and
 * this is a LIST whose length is part of the content. Storing it as
 * {"ar": [...], "en": [...]} would let the two locales end up with
 * different numbers of bullets and no way to pair them up, so the locale
 * nesting goes inside each item instead — see featureList().
 */
class Package extends Model
{
    use HasFactory, HasSlug, HasTranslations, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'price',
        'currency',
        'features',
        'is_popular',
        'sort_order',
        'is_active',
    ];

    /** @var list<string> */
    public $translatable = [
        'name',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'price' => 'decimal:2',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function slugSourceField(): string
    {
        return 'name';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seo_metable');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * True when the package has no published price — "على الطلب" rather
     * than free. NULL and 0.00 mean different things here, so callers get
     * an explicit predicate instead of guessing from a falsy value.
     */
    public function isPriceOnRequest(): bool
    {
        return $this->price === null;
    }

    /**
     * The feature bullets in the current locale, falling back per item.
     * Malformed entries are dropped rather than rendered as "Array".
     *
     * @return list<string>
     */
    public function featureList(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $fallback = config('app.fallback_locale');

        return array_values(array_filter(array_map(
            function ($feature) use ($locale, $fallback) {
                if (is_string($feature)) {
                    return $feature;
                }

                if (! is_array($feature)) {
                    return null;
                }

                $value = $feature[$locale] ?? $feature[$fallback] ?? null;

                return is_string($value) && trim($value) !== '' ? $value : null;
            },
            $this->features ?? []
        )));
    }
}
