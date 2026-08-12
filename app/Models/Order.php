<?php

namespace App\Models;

use App\Models\Concerns\HasTrackingCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A client-portal order.
 *
 * Opening one requires the tracking code, never the reference — see
 * HasTrackingCode for why that distinction is the whole security model of
 * the portal.
 */
class Order extends Model
{
    use HasFactory, HasTrackingCode, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_IN_REVIEW,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Reference alphabet. Deliberately excludes I, O, 0 and 1 — a
     * reference is read aloud and typed off an invoice, and those four are
     * the pairs people get wrong.
     */
    private const REFERENCE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * NOTE what is missing: `reference`, `status`, `progress` and
     * `tracking_code_hash`.
     *
     *   status / progress   an order that could set its own state from a
     *                       request payload would let a client mark their
     *                       own work complete.
     *   tracking_code_hash  one that could set its own code would let an
     *                       attacker choose a known value and then read
     *                       the order back with it.
     *   reference           generated, unique, and quoted publicly; it is
     *                       an identifier the system issues, not an input.
     */
    protected $fillable = [
        'package_id',
        'lead_id',
        'user_id',
        'client_name',
        'client_email',
        'client_phone',
        'total',
        'currency',
        'notes',
        'placed_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'progress' => 0,
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'progress' => 'integer',
            'placed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (empty($order->reference)) {
                $order->reference = static::generateReference();
            }

            // placed_at is NOT NULL with no portable default; an order
            // always has a moment it was placed.
            $order->placed_at ??= now();
        });
    }

    /**
     * A human-shareable id: CM-<year>-<6 chars>.
     *
     * Random rather than sequential, so the reference does not leak how
     * many orders exist — but it is still NOT a credential, and nothing
     * authenticates with it. The retry loop closes the (tiny) collision
     * window against the unique index; withTrashed() because that index
     * counts soft-deleted rows.
     */
    public static function generateReference(): string
    {
        do {
            $suffix = '';

            for ($i = 0; $i < 6; $i++) {
                $suffix .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }

            $reference = 'CM-'.now()->year.'-'.$suffix;
        } while (static::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    // -----------------------------------------------------------------
    // State — the only writers of `status`, `progress` and `completed_at`.
    // -----------------------------------------------------------------

    /**
     * Moves the order and records the move on the timeline in one step, so
     * a status change can never happen without the client seeing why.
     *
     * @param  array<string, string>|null  $note  per-locale
     */
    public function transitionTo(string $status, ?array $note = null, ?int $progress = null): bool
    {
        if (! in_array($status, self::STATUSES, true)) {
            return false;
        }

        $this->status = $status;

        if ($progress !== null) {
            $this->setProgress($progress);
        }

        if ($status === self::STATUS_COMPLETED) {
            $this->progress = 100;
            $this->completed_at ??= now();
        }

        $saved = $this->save();

        if ($saved) {
            $this->events()->create([
                'status' => $status,
                'note' => $note,
                'occurred_at' => now(),
            ]);
        }

        return $saved;
    }

    /**
     * Clamped rather than validated-and-rejected: the column is an
     * unsignedTinyInteger, so an out-of-range write would otherwise be a
     * database error on a value that has an obvious sane interpretation.
     */
    public function setProgress(int $percent): void
    {
        $this->progress = max(0, min(100, $percent));
    }

    public function package(): BelongsTo
    {
        // withTrashed(): Package soft-deletes, and an order must keep
        // showing what was actually bought after the package is retired.
        return $this->belongsTo(Package::class)->withTrashed();
    }

    /** withTrashed() for the same reason — Lead soft-deletes. */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Oldest first: a timeline is read in the order it happened. */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * A masked reference for logs and any screen that does not need the
     * whole thing.
     */
    public function maskedReference(): string
    {
        return Str::mask((string) $this->reference, '*', 3, max(0, strlen((string) $this->reference) - 6));
    }
}
