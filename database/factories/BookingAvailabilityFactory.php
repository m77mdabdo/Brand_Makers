<?php

namespace Database\Factories;

use App\Models\BookingAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingAvailability>
 */
class BookingAvailabilityFactory extends Factory
{
    protected $model = BookingAvailability::class;

    public function definition(): array
    {
        return [
            // day_of_week carries a unique index, so it cycles rather than
            // repeating — ->count(7) produces a full week.
            'day_of_week' => $this->faker->unique()->numberBetween(0, 6),
            'is_open' => true,
            'opens_at' => '09:00:00',
            'closes_at' => '17:00:00',
            'slot_minutes' => 30,
            'capacity' => 1,
        ];
    }

    public function onDay(int $dayOfWeek): static
    {
        return $this->state(fn () => ['day_of_week' => $dayOfWeek]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['is_open' => false]);
    }

    public function withCapacity(int $capacity): static
    {
        return $this->state(fn () => ['capacity' => $capacity]);
    }

    public function hours(string $opensAt, string $closesAt, int $slotMinutes = 30): static
    {
        return $this->state(fn () => [
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'slot_minutes' => $slotMinutes,
        ]);
    }
}
