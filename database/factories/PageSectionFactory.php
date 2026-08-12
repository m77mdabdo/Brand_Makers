<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageSection>
 */
class PageSectionFactory extends Factory
{
    protected $model = PageSection::class;

    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            // Unique because (page_id, key) is a unique index.
            'key' => $this->faker->unique()->slug(2),
            'sort_order' => 0,
            'is_active' => true,
            // title/description/icon are virtual accessors over this JSON
            // blob — they are not columns.
            'content' => [
                'title' => ['ar' => 'عنوان القسم', 'en' => 'Section title'],
                'description' => ['ar' => 'وصف', 'en' => 'Description'],
                'icon' => null,
            ],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
