<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Statuses that occupy a slot. A cancelled booking must release its
     * slot immediately, or one abandoned form fills the calendar.
     *
     * @var list<string>
     */
    public const SLOT_HOLDING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
    ];

    /**
     * NOTE what is missing: `status`.
     *
     * A booking that could confirm itself from a form payload would let an
     * unauthenticated visitor skip the review step entirely, and one that
     * could set itself to `cancelled` could free a slot it still occupies.
     * Status moves only through the explicit transitions below.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'service_id',
        'scheduled_at',
        'notes',
        'lead_id',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    // -----------------------------------------------------------------
    // Status transitions — the only writers of `status`.
    // -----------------------------------------------------------------

    public function markConfirmed(): bool
    {
        return $this->transitionTo(self::STATUS_CONFIRMED);
    }

    public function markCompleted(): bool
    {
        return $this->transitionTo(self::STATUS_COMPLETED);
    }

    public function markCancelled(): bool
    {
        return $this->transitionTo(self::STATUS_CANCELLED);
    }

    protected function transitionTo(string $status): bool
    {
        if (! in_array($status, self::STATUSES, true)) {
            return false;
        }

        $this->status = $status;

        return $this->save();
    }

    /**
     * withTrashed(): Service soft-deletes and the FK is nullOnDelete, so a
     * past appointment keeps naming the service it was booked for after
     * that service is archived.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    /** withTrashed() for the same reason — Lead soft-deletes. */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>=', now())->orderBy('scheduled_at');
    }

    /**
     * Bookings that actually occupy a given instant.
     *
     * This is the capacity check, and the reason there is no unique index
     * on scheduled_at: "at most N rows for this instant, not counting
     * cancelled ones" is not expressible as a database constraint. Rides
     * bookings_scheduled_at_status_index, so it is one indexed read.
     */
    public function scopeHoldingSlot(Builder $query, \DateTimeInterface $slot): Builder
    {
        return $query->where('scheduled_at', $slot)
            ->whereIn('status', self::SLOT_HOLDING_STATUSES);
    }

    public function occupiesSlot(): bool
    {
        return in_array($this->status, self::SLOT_HOLDING_STATUSES, true);
    }
}
