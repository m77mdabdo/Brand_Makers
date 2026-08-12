<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<SeoMeta>
 */
class SeoMetaFactory extends Factory
{
    protected $model = SeoMeta::class;

    public function definition(): array
    {
        return [
            // The morph ALIAS, not Page::class. The map is enforced, so
            // storing the FQCN here would write a type the relation can
            // never resolve back.
            'seo_metable_type' => (new Page)->getMorphClass(),
            'seo_metable_id' => Page::factory(),
            'meta_title' => ['ar' => 'عنوان ميتا', 'en' => $this->faker->sentence(4)],
            'meta_description' => ['ar' => 'وصف ميتا', 'en' => $this->faker->sentence()],
        ];
    }

    /**
     * Attach to any mapped owner: SeoMeta::factory()->for($service, 'seoMetable')
     * works too, but this reads better when the owner already exists.
     */
    public function forOwner(Model $owner): static
    {
        return $this->state(fn () => [
            'seo_metable_type' => $owner->getMorphClass(),
            'seo_metable_id' => $owner->getKey(),
        ]);
    }
}
