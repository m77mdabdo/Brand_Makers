<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function scopeOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query->whereDate('date', $date->toDateString());
    }

    public static function blocks(CarbonInterface $date): bool
    {
        return static::query()->on($date)->exists();
    }
}
