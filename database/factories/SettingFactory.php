<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'group' => 'contact',
            'value' => $this->faker->word(),
        ];
    }

    public function translated(): static
    {
        return $this->state(fn () => [
            'value' => ['ar' => 'قيمة', 'en' => $this->faker->words(2, true)],
        ]);
    }
}
