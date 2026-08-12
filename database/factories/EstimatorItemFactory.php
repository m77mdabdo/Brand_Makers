<?php

namespace Database\Factories;

use App\Models\EstimatorItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstimatorItem>
 */
class EstimatorItemFactory extends Factory
{
    protected $model = EstimatorItem::class;

    public function definition(): array
    {
        $label = $this->faker->unique()->words(2, true);

        return [
            'key' => $this->faker->unique()->slug(2),
            'label' => ['ar' => 'بند '.$label, 'en' => ucfirst($label)],
            'group' => 'identity',
            'price' => $this->faker->randomFloat(2, 250, 12000),
            'currency' => 'SAR',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inGroup(string $group): static
    {
        return $this->state(fn () => ['group' => $group]);
    }

    public function pricedAt(float $price): static
    {
        return $this->state(fn () => ['price' => $price]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
