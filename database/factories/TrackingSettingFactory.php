<?php

namespace Database\Factories;

use App\Models\TrackingSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackingSetting>
 */
class TrackingSettingFactory extends Factory
{
    protected $model = TrackingSetting::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'value' => $this->faker->numerify('###############'),
            // A fresh row is inert: nothing reaches the page until someone
            // deliberately enables it.
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    /**
     * A real key with a value that satisfies TrackingSetting::FORMATS, for
     * asserting the validation accepts what it should.
     */
    public function metaPixel(): static
    {
        return $this->state(fn () => ['key' => 'meta_pixel_id', 'value' => '123456789012345']);
    }
}
