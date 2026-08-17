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
        /*
         * NOT `price` or `currency`. Both are admin-only figures that end
         * up on the public pricing surfaces, and there is no public write
         * path to a Service — but leaving them out of $fillable means a
         * crafted payload still cannot reach them even if one is added
         * later, or if a controller somewhere starts passing request data
         * straight to create(). ServiceContentService assigns them
         * explicitly instead; see its WRITABLE list.
         */
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
            'price' => 'decimal:2',
        ];
    }

    /**
     * True when this service has no agreed figure yet.
     *
     * Same meaning as Package::isPriceOnRequest() and
     * EstimatorItem::isPriceOnRequest(): null is "على الطلب", which is a
     * different fact from 0.00 (free). An on-request service is still
     * offered and still selectable in the estimator — it turns the quote
     * into a floor rather than contributing a number.
     */
    public function isPriceOnRequest(): bool
    {
        return $this->price === null;
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
