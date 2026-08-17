<?php

namespace Database\Factories;

use App\Models\EstimatorGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EstimatorGroup>
 */
class EstimatorGroupFactory extends Factory
{
    protected $model = EstimatorGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = Str::slug($this->faker->unique()->words(2, true));

        return [
            'key' => $key,
            'label' => ['ar' => 'مجموعة '.$key, 'en' => 'Group '.$key],
            'selection' => EstimatorGroup::SELECTION_MULTI,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function single(): static
    {
        return $this->state(fn () => ['selection' => EstimatorGroup::SELECTION_SINGLE]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
