<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Country;
use App\Models\Package;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasSlugTest extends TestCase
{
    use RefreshDatabase;

    private function makeArticle(string $title): Article
    {
        return Article::create([
            'title' => ['ar' => $title],
            'body' => ['ar' => '<p>نص</p>'],
            'is_published' => true,
        ]);
    }

    private function makeService(string $name): Service
    {
        return Service::create([
            'name' => ['ar' => $name],
            'summary' => ['ar' => 'ملخص'],
            'body' => ['ar' => '<p>نص</p>'],
            'requirements' => ['ar' => 'متطلبات'],
            'process' => ['ar' => 'خطوات'],
            'is_active' => true,
        ]);
    }

    /**
     * BUG-1, baked in from day one. The DB unique index on `slug` counts
     * soft-deleted rows, so a uniqueness check that runs through the
     * default global scope thinks the slug is free and the insert dies
     * with a UniqueConstraintViolationException.
     */
    public function test_a_slug_does_not_collide_with_a_soft_deleted_article(): void
    {
        $first = $this->makeArticle('مقال مكرر');
        $first->delete();

        $this->assertSoftDeleted($first);

        $second = $this->makeArticle('مقال مكرر');

        $this->assertNotSame($first->slug, $second->slug);
        $this->assertSame($first->slug.'-2', $second->slug);
    }

    public function test_a_slug_does_not_collide_with_a_soft_deleted_service(): void
    {
        $first = $this->makeService('خدمة مكررة');
        $first->delete();

        $second = $this->makeService('خدمة مكررة');

        $this->assertSame($first->slug.'-2', $second->slug);
    }

    public function test_a_slug_does_not_collide_with_a_soft_deleted_package(): void
    {
        $first = Package::create(['name' => ['ar' => 'باقة مكررة']]);
        $first->delete();

        $second = Package::create(['name' => ['ar' => 'باقة مكررة']]);

        $this->assertSame($first->slug.'-2', $second->slug);
    }

    public function test_a_slug_still_increments_against_live_rows(): void
    {
        $first = $this->makeArticle('مقال حي');
        $second = $this->makeArticle('مقال حي');
        $third = $this->makeArticle('مقال حي');

        $this->assertSame($first->slug.'-2', $second->slug);
        $this->assertSame($first->slug.'-3', $third->slug);
    }

    /**
     * Country uses HasSlug WITHOUT SoftDeletes — withTrashed() would throw
     * on it, so the trait must only reach for it when the model actually
     * soft-deletes.
     */
    public function test_slug_generation_works_on_a_model_without_soft_deletes(): void
    {
        $first = Country::create(['name' => ['ar' => 'دولة مكررة']]);
        $second = Country::create(['name' => ['ar' => 'دولة مكررة']]);

        $this->assertSame($first->slug.'-2', $second->slug);
    }

    public function test_an_explicitly_provided_slug_is_never_overwritten(): void
    {
        $article = Article::create([
            'slug' => 'a-slug-i-chose',
            'title' => ['ar' => 'عنوان مختلف'],
            'body' => ['ar' => '<p>نص</p>'],
            'is_published' => true,
        ]);

        $this->assertSame('a-slug-i-chose', $article->slug);
    }

    /**
     * A title that transliterates to nothing (punctuation, emoji, a script
     * Str::slug drops entirely) must not make every such record collide on
     * an empty slug and then queue up behind "-2", "-3", …
     */
    public function test_a_title_that_slugifies_to_nothing_still_gets_a_distinct_slug(): void
    {
        $first = $this->makeArticle('؟؟؟');
        $second = $this->makeArticle('؟؟؟');

        $this->assertNotSame('', $first->slug);
        $this->assertNotSame($first->slug, $second->slug);
        $this->assertMatchesRegularExpression('/^item-[a-z0-9]{6}$/', $first->slug);
    }
}
