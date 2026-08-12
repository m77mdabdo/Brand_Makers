<?php

namespace App\Models;

use App\Models\Concerns\AnonymizesIp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use AnonymizesIp, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /**
     * `status` stays fillable — unlike order/booking status it is set only
     * by an authenticated moderator action, and the public submission path
     * relies on the column default rather than passing a value. What is
     * NOT fillable is nothing here; the guard that matters for this table
     * is the ip_address mutator below, which no caller can bypass.
     */
    protected $fillable = [
        'article_id',
        'name',
        'email',
        'body',
        'status',
        'ip_address',
    ];

    /**
     * Mirrors the column default so a just-built Comment reports 'pending'
     * before it is re-fetched.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * withTrashed(): Article soft-deletes, and the moderation queue must
     * still be able to say which article a comment belongs to after that
     * article is archived. Without it the relation resolves to null and
     * the dashboard shows a blank row it cannot act on.
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class)->withTrashed();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
