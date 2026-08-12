<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The weekly booking window for one weekday.
 *
 * day_of_week is 0 = Sunday … 6 = Saturday, matching Carbon::dayOfWeek, so
 * there is no translation layer between this config and the date maths.
 */
class BookingAvailability extends Model
{
    use HasFactory;

    protected $table = 'booking_availability';

    public const SUNDAY = 0;

    public const MONDAY = 1;

    public const TUESDAY = 2;

    public const WEDNESDAY = 3;

    public const THURSDAY = 4;

    public const FRIDAY = 5;

    public const SATURDAY = 6;

    protected $fillable = [
        'day_of_week',
        'is_open',
        'opens_at',
        'closes_at',
        'slot_minutes',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_open' => 'boolean',
            'slot_minutes' => 'integer',
            'capacity' => 'integer',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_open', true);
    }

    public function scopeForDate(Builder $query, CarbonInterface $date): Builder
    {
        return $query->where('day_of_week', $date->dayOfWeek);
    }

    /**
     * The slot start times this day generates, as "HH:MM" strings.
     *
     * A slot is only produced if it FITS entirely before closing time —
     * a 30-minute slot at 16:45 against a 17:00 close would otherwise be
     * offered and then overrun. Returns an empty list for a closed day, or
     * for a nonsensical window, rather than looping forever.
     *
     * @return list<string>
     */
    public function slots(): array
    {
        if (! $this->is_open || $this->slot_minutes < 1) {
            return [];
        }

        $cursor = $this->timeToMinutes($this->opens_at);
        $close = $this->timeToMinutes($this->closes_at);

        if ($cursor === null || $close === null || $close <= $cursor) {
            return [];
        }

        $slots = [];

        while ($cursor + $this->slot_minutes <= $close) {
            $slots[] = sprintf('%02d:%02d', intdiv($cursor, 60), $cursor % 60);
            $cursor += $this->slot_minutes;
        }

        return $slots;
    }

    private function timeToMinutes(mixed $time): ?int
    {
        if (! is_string($time) || ! preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }
}
