<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'key',
        'sort_order',
        'content',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
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
     * content is a single JSON blob (not spatie/laravel-translatable), so
     * title/description need their own locale-aware accessors rather than
     * getTranslation(). Falls back to the app fallback locale, since every
     * section is required to have a value there.
     */
    protected function title(): Attribute
    {
        return Attribute::make(get: fn () => $this->localised('title'));
    }

    protected function description(): Attribute
    {
        return Attribute::make(get: fn () => $this->localised('description'));
    }

    protected function icon(): Attribute
    {
        return Attribute::make(get: fn () => $this->content['icon'] ?? null);
    }

    private function localised(string $key): ?string
    {
        $fallback = config('app.fallback_locale');

        return $this->content[$key][app()->getLocale()]
            ?? $this->content[$key][$fallback]
            ?? null;
    }
}
