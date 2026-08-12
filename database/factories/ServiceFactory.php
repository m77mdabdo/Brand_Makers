<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        // Both locales are populated so tests can assert per-locale
        // behaviour without extra setup.
        $name = $this->faker->unique()->words(3, true);

        return [
            'slug' => $this->faker->unique()->slug(),
            'name' => ['ar' => 'خدمة '.$name, 'en' => ucfirst($name)],
            'summary' => ['ar' => 'ملخص', 'en' => 'Summary'],
            'body' => ['ar' => '<p>نص</p>', 'en' => '<p>Body</p>'],
            'requirements' => ['ar' => 'متطلبات', 'en' => 'Requirements'],
            'process' => ['ar' => 'خطوات', 'en' => 'Process'],
            'is_active' => true,
            'is_flagship' => false,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function flagship(): static
    {
        return $this->state(fn () => ['is_flagship' => true]);
    }
}
