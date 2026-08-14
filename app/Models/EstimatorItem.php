<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Translatable\HasTranslations;

/**
 * One selectable line in the cost estimator.
 *
 * `key` is the stable identifier — the label can be rewritten freely
 * without invalidating anything that references the item.
 */
class EstimatorItem extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'key',
        'label',
        'group',
        'price',
        'currency',
        'is_active',
        'sort_order',
    ];

    /** @var list<string> */
    public $translatable = ['label'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('group')->orderBy('sort_order')->orderBy('id');
    }

    public function scopeInGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * The whole estimator form in one indexed read, grouped for rendering.
     *
     * Ordered by sort_order ALONE, not by group first.
     *
     * groupBy() preserves the order the rows arrive in, so the groups come
     * out in the order their first item does — which is the sequence the
     * catalogue was written in (identity, strategy, print, digital,
     * content). Sorting by `group` first would order them alphabetically
     * by slug instead, putting "content" at the top of a page whose whole
     * argument starts with visual identity.
     *
     * scopeOrdered() keeps its group-first sort: that one is for the
     * admin list, where alphabetical grouping is what you want to scan.
     *
     * @return Collection<string, Collection<int, static>>
     */
    public static function grouped(): Collection
    {
        return static::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('group');
    }

    /**
     * The estimate for a set of keys.
     *
     * Deliberately re-reads the prices from the database rather than
     * trusting anything the client sent: a total computed from a submitted
     * price is a total the visitor chose. Unknown and inactive keys are
     * ignored rather than erroring, so a stale bookmarked selection still
     * produces a sensible figure.
     *
     * @param  array<int, string>  $keys
     */
    public static function estimateFor(array $keys): string
    {
        $total = static::query()
            ->active()
            ->whereIn('key', array_filter($keys, 'is_string'))
            ->sum('price');

        return number_format((float) $total, 2, '.', '');
    }
}
