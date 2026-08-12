<?php

namespace Database\Factories;

use App\Models\ConversionEvent;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversionEvent>
 */
class ConversionEventFactory extends Factory
{
    protected $model = ConversionEvent::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'event_type' => 'contract_signed',
            'value' => $this->faker->randomFloat(2, 1000, 50000),
            'currency' => 'SAR',
            // occurred_at is NOT NULL with no portable default — SQLite
            // rejects an insert that omits it, so never leave it out.
            'occurred_at' => now()->subDay(),
        ];
    }

    /**
     * A milestone that is deliberately NOT a won type, for asserting the
     * converted badge and revenue totals exclude it.
     */
    public function milestone(): static
    {
        return $this->state(fn () => ['event_type' => 'meeting_booked', 'value' => null]);
    }
}
