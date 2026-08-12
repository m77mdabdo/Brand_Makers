<?php

namespace Database\Factories;

use App\Models\LeadSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadSource>
 */
class LeadSourceFactory extends Factory
{
    protected $model = LeadSource::class;

    public function definition(): array
    {
        $key = $this->faker->unique()->slug(1);

        return [
            'key' => $key,
            'label' => ucfirst($key),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /** is_system is guarded — assigned, never filled. */
    public function system(): static
    {
        return $this->afterMaking(fn (LeadSource $source) => $source->is_system = true)
            ->afterCreating(function (LeadSource $source) {
                $source->is_system = true;
                $source->save();
            });
    }
}
