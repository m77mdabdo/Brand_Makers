<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'body' => $this->faker->paragraph(),
            // Public submissions are always created pending; approval is
            // an explicit moderation action.
            'status' => Comment::STATUS_PENDING,
            // Stored truncated whatever is passed — the model mutator
            // anonymizes it, which is what the IP test asserts.
            'ip_address' => $this->faker->ipv4(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => Comment::STATUS_APPROVED]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => Comment::STATUS_REJECTED]);
    }
}
