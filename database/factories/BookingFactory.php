<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => '+9665'.$this->faker->numerify('########'),
            'service_id' => Service::factory(),
            // On a slot boundary, so capacity assertions compare equal
            // instants rather than near-misses.
            'scheduled_at' => now()->addWeek()->startOfHour(),
            'notes' => null,
            'lead_id' => null,
        ];
    }

    public function at(\DateTimeInterface|string $when): static
    {
        return $this->state(fn () => ['scheduled_at' => $when]);
    }

    // status is guarded, so every state below assigns it rather than
    // filling it — exactly the path application code has to take.

    public function confirmed(): static
    {
        return $this->withStatus(Booking::STATUS_CONFIRMED);
    }

    public function completed(): static
    {
        return $this->withStatus(Booking::STATUS_COMPLETED);
    }

    public function cancelled(): static
    {
        return $this->withStatus(Booking::STATUS_CANCELLED);
    }

    private function withStatus(string $status): static
    {
        return $this->afterMaking(fn (Booking $booking) => $booking->status = $status)
            ->afterCreating(function (Booking $booking) use ($status) {
                $booking->status = $status;
                $booking->save();
            });
    }
}
