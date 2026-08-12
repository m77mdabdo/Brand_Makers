<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Translatable\HasTranslations;

/**
 * Per-record SEO overrides.
 *
 * seo_metable_type holds a morph ALIAS ('page', 'article', …), not a
 * class name — AppServiceProvider enforces the map, so writing a record
 * for an unmapped model is a loud error rather than a row that silently
 * stops resolving after the next namespace change.
 */
class SeoMeta extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'seo_meta';

    protected $fillable = [
        'seo_metable_type',
        'seo_metable_id',
        'meta_title',
        'meta_description',
        'og_image',
        'canonical_url',
    ];

    /** @var list<string> */
    public $translatable = [
        'meta_title',
        'meta_description',
    ];

    public function seoMetable(): MorphTo
    {
        return $this->morphTo();
    }
}
