<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(4);

        return [
            'slug' => $this->faker->unique()->slug(),
            'title' => ['ar' => 'مقال '.$title, 'en' => $title],
            'excerpt' => ['ar' => 'مقتطف', 'en' => $this->faker->sentence()],
            'body' => ['ar' => '<p>محتوى</p>', 'en' => '<p>'.$this->faker->paragraph().'</p>'],
            'is_published' => true,
            // In the past: the public blog filters on published_at <= now().
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false, 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['is_published' => true, 'published_at' => now()->addWeek()]);
    }
}
