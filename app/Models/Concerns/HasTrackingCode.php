<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * An unguessable, write-once lookup credential — stored only as a digest.
 *
 * The plaintext is generated from random_bytes() (the CSPRNG, never
 * mt_rand/uniqid/Str::random-with-a-seed), handed back exactly once at
 * creation, and then unrecoverable: the row holds nothing but the SHA-256
 * of it. A database dump therefore yields no working portal links, and
 * nobody with table access can read a client's order.
 *
 * WHY SHA-256 AND NOT BCRYPT/ARGON. Slow hashes exist because passwords
 * are low-entropy and guessable. This code carries a full 256 bits from a
 * CSPRNG — there is no dictionary and no brute force to slow down, so a
 * slow hash would buy nothing but a per-request cost. A plain digest also
 * keeps lookup to ONE indexed equality read, which is the property that
 * makes the portal enumeration-resistant: the presented code is hashed and
 * matched directly, so there is no scan, no per-row comparison, and no
 * timing difference between "no such order" and "wrong code".
 */
trait HasTrackingCode
{
    /**
     * 32 bytes = 256 bits, rendered as 64 lowercase hex characters. Hex
     * rather than base64url so the code is case-insensitive to type and
     * carries no characters that need URL-encoding.
     */
    public const TRACKING_CODE_BYTES = 32;

    /**
     * Set for the lifetime of the object that issued it, and never
     * persisted, serialised or logged. Read it once via
     * trackingCode() — after that the value is gone.
     */
    protected ?string $plainTrackingCode = null;

    protected static function bootHasTrackingCode(): void
    {
        static::creating(function ($model) {
            if (empty($model->tracking_code_hash)) {
                $model->issueTrackingCode();
            }
        });
    }

    /**
     * Mints a new code, stores its digest on the model, and returns the
     * plaintext. Calling this on an existing record invalidates the old
     * code — which is the intended way to revoke a leaked link.
     */
    public function issueTrackingCode(): string
    {
        $plain = static::generateTrackingCode();

        $this->plainTrackingCode = $plain;
        $this->attributes['tracking_code_hash'] = static::hashTrackingCode($plain);

        return $plain;
    }

    public static function generateTrackingCode(): string
    {
        return bin2hex(random_bytes(self::TRACKING_CODE_BYTES));
    }

    public static function hashTrackingCode(string $code): string
    {
        return hash('sha256', strtolower(trim($code)));
    }

    /**
     * The plaintext, if this object is the one that issued it. Null on any
     * model read back from the database — by design.
     */
    public function trackingCode(): ?string
    {
        return $this->plainTrackingCode;
    }

    /**
     * Never expose the digest through toArray()/toJson(). It is not the
     * code, but it is the only thing standing between a leaked API
     * response and every order in the table.
     */
    public function initializeHasTrackingCode(): void
    {
        $this->hidden = array_values(array_unique([...$this->hidden, 'tracking_code_hash']));
    }

    /**
     * Malformed input is rejected before it reaches the database — a
     * lookup that cannot possibly match should not cost a query.
     */
    public static function isWellFormedTrackingCode(string $code): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{'.(self::TRACKING_CODE_BYTES * 2).'}$/i',
            trim($code)
        );
    }

    public function scopeForTrackingCode(Builder $query, string $code): Builder
    {
        return $query->where('tracking_code_hash', static::hashTrackingCode($code));
    }

    /**
     * The only supported way to resolve a code to a record.
     *
     * Note what is NOT here: no lookup by `reference`. The reference is
     * short, sequential-ish and quoted over the phone, so treating it as a
     * credential would make the portal trivially enumerable.
     */
    public static function findByTrackingCode(?string $code): ?static
    {
        if ($code === null || ! static::isWellFormedTrackingCode($code)) {
            return null;
        }

        return static::query()->forTrackingCode($code)->first();
    }
}
