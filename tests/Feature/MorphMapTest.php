<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Country;
use App\Models\Package;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Providers\AppServiceProvider;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The morph map is ENFORCED, not merely declared.
 *
 * Two things follow. seo_meta stores a short alias, so renaming or moving
 * a model class never orphans the rows pointing at it. And attaching SEO
 * metadata to a model nobody added to the map throws immediately, instead
 * of writing a row that silently resolves to nothing forever after.
 */
class MorphMapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function mappedModels(): array
    {
        return [
            'Page' => [Page::class, 'page'],
            'Article' => [Article::class, 'article'],
            'Service' => [Service::class, 'service'],
            'Country' => [Country::class, 'country'],
            'Package' => [Package::class, 'package'],
        ];
    }

    #[DataProvider('mappedModels')]
    public function test_the_model_stores_its_alias_and_resolves_back(string $model, string $alias): void
    {
        $owner = $model::factory()->create();

        $this->assertSame($alias, $owner->getMorphClass());

        $owner->seoMeta()->create(['meta_title' => ['ar' => 'عنوان']]);

        $this->assertDatabaseHas('seo_meta', [
            'seo_metable_type' => $alias,
            'seo_metable_id' => $owner->getKey(),
        ]);

        $this->assertTrue(SeoMeta::first()->seoMetable->is($owner));
    }

    public function test_no_fully_qualified_class_name_is_ever_stored(): void
    {
        foreach (array_keys(self::mappedModels()) as $key) {
            [$model] = self::mappedModels()[$key];
            $model::factory()->create()->seoMeta()->create(['meta_title' => ['ar' => 'ع']]);
        }

        $this->assertSame(
            0,
            SeoMeta::where('seo_metable_type', 'like', 'App\\\\Models\\\\%')->count(),
            'A stored class name would break the moment the class moved.'
        );
    }

    public function test_an_unmapped_model_cannot_be_attached_polymorphically(): void
    {
        $this->expectException(ClassMorphViolationException::class);

        // Comment carries no SEO metadata and is deliberately absent from
        // the map — enforcement is what turns that into a loud error.
        (new Comment)->getMorphClass();
    }

    public function test_the_provider_map_is_the_one_actually_registered(): void
    {
        $this->assertSame(AppServiceProvider::MORPH_MAP, Relation::morphMap());
    }

    public function test_one_owner_cannot_hold_two_competing_seo_records(): void
    {
        $page = Page::factory()->create();
        $page->seoMeta()->create(['meta_title' => ['ar' => 'الأول']]);

        $this->expectException(UniqueConstraintViolationException::class);

        $page->seoMeta()->create(['meta_title' => ['ar' => 'الثاني']]);
    }
}
