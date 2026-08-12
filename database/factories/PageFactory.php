<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'slug' => $this->faker->unique()->slug(),
            'title' => ['ar' => 'صفحة '.$title, 'en' => $title],
            'body' => ['ar' => '<p>نص</p>', 'en' => '<p>Body</p>'],
            'meta_title' => ['ar' => 'عنوان', 'en' => $title],
            'meta_description' => ['ar' => 'وصف', 'en' => $this->faker->sentence()],
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
