<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A group of estimator options, rendered as one selector.
 *
 * `key` is the stable identifier that estimator_items.group points at;
 * the label can be rewritten freely without breaking that join.
 */
class EstimatorGroup extends Model
{
    use HasFactory, HasTranslations;

    /**
     * One answer, or several.
     *
     * SINGLE renders a <select>: "نوع التأسيس" cannot be two things at
     * once, and a checkbox list there would let a visitor total two base
     * tiers. MULTI renders a multi-select for the groups where picking
     * several is the point.
     *
     * @var list<string>
     */
    public const SELECTION_SINGLE = 'single';

    public const SELECTION_MULTI = 'multi';

    /** @var list<string> */
    public const SELECTIONS = [self::SELECTION_SINGLE, self::SELECTION_MULTI];

    protected $fillable = [
        'key',
        'label',
        'selection',
        'sort_order',
        'is_active',
    ];

    /** @var list<string> */
    public $translatable = ['label'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The items in this group.
     *
     * Joined on the slug rather than an id, because that is what
     * estimator_items has carried since before groups were rows — see the
     * create_estimator_groups migration for why there is no foreign key.
     */
    public function items(): HasMany
    {
        return $this->hasMany(EstimatorItem::class, 'group', 'key');
    }

    public function activeItems(): HasMany
    {
        return $this->items()->active();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function allowsMultiple(): bool
    {
        return $this->selection !== self::SELECTION_SINGLE;
    }

    /**
     * What to call a group that has no row of its own.
     *
     * An item may name a group nobody has created — the two are joined on
     * a slug the admin types, so that is a normal state during a rename,
     * not a corruption. The label falls back to the lang file (where the
     * seeded groups used to live) and then to the raw slug, so the
     * selector always has a heading rather than an empty one.
     */
    public static function labelFor(string $key): string
    {
        $group = static::where('key', $key)->first();

        if ($group) {
            return $group->label;
        }

        $translation = __('site.estimator.groups.'.$key);

        return is_string($translation) && $translation !== 'site.estimator.groups.'.$key
            ? $translation
            : $key;
    }
}
