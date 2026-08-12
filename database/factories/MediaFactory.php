<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'path' => 'media/'.$this->faker->unique()->lexify('????????').'.jpg',
            'disk' => 'public',
            'mime_type' => 'image/jpeg',
            'size' => $this->faker->numberBetween(1000, 500000),
            'type' => 'image',
            'alt_text' => $this->faker->words(3, true),
            'uploaded_by' => null,
        ];
    }
}
