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

class Service extends Model
{
    use HasFactory, HasSlug, HasTranslations, SoftDeletes;

    protected $fillable = [
        'slug',
        'icon',
        'cover_image',
        'is_flagship',
        'sort_order',
        'is_active',
        'name',
        'summary',
        'body',
        'requirements',
        'process',
    ];

    /** @var list<string> */
    public $translatable = [
        'name',
        'summary',
        'body',
        'requirements',
        'process',
    ];

    protected function casts(): array
    {
        return [
            'is_flagship' => 'boolean',
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

    /**
     * Live enquiries only. This is the one direction that does NOT reach
     * for withTrashed(): an archived lead should not count towards a
     * service's enquiry volume. The withTrashed() rule applies to the
     * belongsTo side, where the child is a historical record that must
     * keep naming its archived parent.
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'requested_service_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
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
}
