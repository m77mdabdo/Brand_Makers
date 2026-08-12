<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * One line on an order's status timeline.
 *
 * `status` records what was said at the time rather than being derived
 * from the order's current state — a timeline that rewrites itself when
 * the order moves on is not a timeline.
 */
class OrderEvent extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'order_id',
        'status',
        'note',
        'occurred_at',
    ];

    /** @var list<string> */
    public $translatable = ['note'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * withTrashed(): Order soft-deletes, and an event row outlives that —
     * without this, every event on an archived order resolves to a null
     * order and the timeline cannot even name what it belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }
}
