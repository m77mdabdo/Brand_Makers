<?php

namespace Database\Factories;

use App\Models\BookingBlackoutDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingBlackoutDate>
 */
class BookingBlackoutDateFactory extends Factory
{
    protected $model = BookingBlackoutDate::class;

    public function definition(): array
    {
        return [
            // Unique offsets: `date` carries a unique index.
            'date' => now()->addDays($this->faker->unique()->numberBetween(1, 365))->toDateString(),
            'reason' => ['ar' => 'إجازة رسمية', 'en' => 'Public holiday'],
        ];
    }

    public function on(string $date): static
    {
        return $this->state(fn () => ['date' => $date]);
    }
}
