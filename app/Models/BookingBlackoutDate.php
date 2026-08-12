<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * A single date on which nothing can be booked, whatever the weekly
 * availability says.
 */
class BookingBlackoutDate extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'date',
        'reason',
    ];

    /** @var list<string> */
    public $translatable = ['reason'];

    /**
     * A hand-written date-only accessor/mutator rather than the framework's
     * 'date' cast, because the cast is not portable for a DATE column.
     *
     * Eloquent formats a date attribute with the connection's full datetime
     * format on write, producing "2026-08-29 00:00:00". MySQL's DATE column
     * silently truncates that back to "2026-08-29"; SQLite has no real DATE
     * type and stores the string verbatim. The two engines therefore hold
     * DIFFERENT values, and a plain `where('date', '2026-08-29')` — or an
     * updateOrCreate keyed on it — matches on MySQL and misses on SQLite,
     * which then trips the unique index instead of updating.
     *
     * Normalising on write makes the stored value identical everywhere, so
     * equality lookups behave the same on both. This matters more here than
     * on any other date column in the schema, because `date` is unique and
     * is what the blackout check keys on.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : CarbonImmutable::parse($value)->startOfDay(),
            set: fn (mixed $value) => $value === null ? null : CarbonImmutable::parse($value)->toDateString(),
        );
    }

    public function scopeOn(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query->where('date', CarbonImmutable::parse($date)->toDateString());
    }

    public static function blocks(CarbonInterface|string $date): bool
    {
        return static::query()->on($date)->exists();
    }
}
