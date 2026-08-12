<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->country();

        return [
            'slug' => $this->faker->unique()->slug(),
            'name' => ['ar' => 'دولة '.$name, 'en' => $name],
            'notes' => ['ar' => 'ملاحظات', 'en' => 'Notes'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
