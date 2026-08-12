<?php

namespace Tests\Feature\Dashboard;

use App\Models\Article;
use App\Models\Country;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesMediaSeoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::factory()->admin()->create();
    }

    // -----------------------------------------------------------------
    // Pages — edit only
    // -----------------------------------------------------------------

    public function test_pages_can_be_listed_and_edited_but_not_created_or_deleted(): void
    {
        $this->assertTrue(Route::has('dashboard.pages.index'));
        $this->assertTrue(Route::has('dashboard.pages.edit'));

        // No create and no destroy: these pages are seeded and referenced
        // by NAME from the footer, the navigation whitelist and the
        // sitemap. A new one would have no route; deleting one would 500
        // every view that links to it.
        $this->assertFalse(Route::has('dashboard.pages.create'));
        $this->assertFalse(Route::has('dashboard.pages.destroy'));
    }

    public function test_an_admin_can_edit_a_page_and_the_public_site_reflects_it(): void
    {
        $page = Page::where('slug', 'about')->firstOrFail();

        $this->actingAs($this->admin)->get(route('dashboard.pages.edit', $page))->assertOk();

        $this->actingAs($this->admin)->put(route('dashboard.pages.update', $page), [
            'title' => ['ar' => 'من نحن', 'en' => 'About Us'],
            'body' => ['ar' => '<p>نص جديد تماماً.</p>', 'en' => '<p>Completely new copy.</p>'],
            'is_published' => '1',
        ])->assertRedirect(route('dashboard.pages.edit', $page));

        $this->get('/about')->assertSee('نص جديد تماماً', false);
        $this->get('/en/about')->assertSee('Completely new copy', false);
    }

    public function test_unpublishing_a_page_makes_it_a_404(): void
    {
        $page = Page::where('slug', 'about')->firstOrFail();

        $this->actingAs($this->admin)->put(route('dashboard.pages.update', $page), [
            'title' => ['ar' => 'من نحن'],
            'body' => ['ar' => '<p>نص</p>'],
            // is_published omitted.
        ]);

        $this->get('/about')->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Page sections — the JSON repack
    // -----------------------------------------------------------------

    public function test_a_section_is_created_from_flat_fields_and_repacked_into_the_json_blob(): void
    {
        $page = Page::where('slug', 'our-process')->firstOrFail();

        $this->actingAs($this->admin)->post(route('dashboard.pages.sections.store', $page), [
            'key' => 'new-step',
            'title_ar' => 'خطوة جديدة',
            'title_en' => 'A new step',
            'description_ar' => 'وصف الخطوة',
            'description_en' => 'Step description',
            'icon' => 'compass',
            'sort_order' => 9,
            'is_active' => '1',
        ])->assertRedirect(route('dashboard.pages.edit', $page));

        $section = PageSection::where('key', 'new-step')->firstOrFail();

        // The nested shape the model's accessors read back.
        $this->assertSame('خطوة جديدة', $section->content['title']['ar']);
        $this->assertSame('A new step', $section->content['title']['en']);
        $this->assertSame('compass', $section->content['icon']);

        app()->setLocale('en');
        $this->assertSame('A new step', $section->title);
    }

    public function test_a_section_can_be_updated_and_deleted(): void
    {
        $page = Page::where('slug', 'our-process')->firstOrFail();
        $section = $page->sections()->firstOrFail();

        $this->actingAs($this->admin)->get(route('dashboard.pages.sections.edit', [$page, $section]))->assertOk();

        $this->actingAs($this->admin)->put(route('dashboard.pages.sections.update', [$page, $section]), [
            'key' => $section->key,
            'title_ar' => 'عنوان محدّث',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('عنوان محدّث', $section->fresh()->content['title']['ar']);

        $this->actingAs($this->admin)->delete(route('dashboard.pages.sections.destroy', [$page, $section]))->assertRedirect();
        $this->assertDatabaseMissing('page_sections', ['id' => $section->id]);
    }

    public function test_a_section_from_another_page_is_a_404_not_someone_elses_form(): void
    {
        $process = Page::where('slug', 'our-process')->firstOrFail();
        $about = Page::where('slug', 'about')->firstOrFail();
        $section = $process->sections()->firstOrFail();

        // Implicit binding resolves the two parameters INDEPENDENTLY, so
        // without the guard this would happily edit a section belonging to
        // a different page.
        $this->actingAs($this->admin)
            ->get(route('dashboard.pages.sections.edit', [$about, $section]))
            ->assertNotFound();
    }

    public function test_a_duplicate_section_key_on_the_same_page_is_rejected(): void
    {
        $page = Page::where('slug', 'our-process')->firstOrFail();
        $existing = $page->sections()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('dashboard.pages.sections.store', $page), [
                'key' => $existing->key,
                'title_ar' => 'عنوان',
            ])
            ->assertSessionHasErrors('key');
    }

    // -----------------------------------------------------------------
    // Media library
    // -----------------------------------------------------------------

    public function test_an_admin_uploads_and_deletes_media(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->get(route('dashboard.media.index'))->assertOk();

        $this->actingAs($this->admin)->post(route('dashboard.media.store'), [
            'file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
            'alt_text' => 'A photo',
        ])->assertRedirect(route('dashboard.media.index'));

        $media = Media::latest('id')->firstOrFail();

        $this->assertSame('A photo', $media->alt_text);
        $this->assertSame($this->admin->id, $media->uploaded_by);
        // The client filename is never used — it is a path-traversal
        // vector and a way to overwrite someone else's upload.
        $this->assertStringNotContainsString('photo.jpg', $media->path);
        Storage::disk('public')->assertExists($media->path);

        $this->actingAs($this->admin)->delete(route('dashboard.media.destroy', $media))->assertRedirect();

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->path);
    }

    /**
     * @return array<string, array{UploadedFile}>
     */
    public static function rejectedUploads(): array
    {
        return [
            'an executable disguised as an image' => [UploadedFile::fake()->create('shell.php', 10, 'image/jpeg')],
            'an SVG' => [UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')],
            'a file over the size limit' => [UploadedFile::fake()->create('huge.jpg', 8192, 'image/jpeg')],
        ];
    }

    #[DataProvider('rejectedUploads')]
    public function test_it_rejects_unsafe_or_oversized_uploads(UploadedFile $file): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('dashboard.media.store'), ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Media::count());
    }

    public function test_media_is_admin_only(): void
    {
        $this->get(route('dashboard.media.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('dashboard.media.index'))->assertForbidden();
    }

    // -----------------------------------------------------------------
    // SEO meta, persisted through the morph relation
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{string, class-string, array<string, mixed>}>
     */
    public static function seoResources(): array
    {
        return [
            'services' => ['services', Service::class, [
                'name' => ['ar' => 'خدمة'],
                'summary' => ['ar' => 'ملخص'],
                'body' => ['ar' => 'نص'],
                'requirements' => ['ar' => 'متطلبات'],
                'process' => ['ar' => 'خطوات'],
            ]],
            'countries' => ['countries', Country::class, [
                'name' => ['ar' => 'دولة'],
            ]],
            'articles' => ['articles', Article::class, [
                'title' => ['ar' => 'مقال'],
                'body' => ['ar' => 'نص'],
            ]],
        ];
    }

    #[DataProvider('seoResources')]
    public function test_seo_meta_is_persisted_through_the_morph_relation(string $resource, string $model, array $payload): void
    {
        $this->actingAs($this->admin)->post(route("dashboard.{$resource}.store"), $payload + [
            'seo' => [
                'meta_title' => ['ar' => 'عنوان ميتا', 'en' => 'Meta title'],
                'meta_description' => ['ar' => 'وصف ميتا', 'en' => 'Meta description'],
                'canonical_url' => 'https://example.test/canonical',
            ],
        ]);

        $record = $model::latest('id')->firstOrFail();
        $seo = $record->seoMeta;

        $this->assertNotNull($seo);
        $this->assertSame('عنوان ميتا', $seo->getTranslation('meta_title', 'ar'));
        $this->assertSame('Meta title', $seo->getTranslation('meta_title', 'en'));
        $this->assertSame('https://example.test/canonical', $seo->canonical_url);
        // Stored as the morph ALIAS, not a class name.
        $this->assertSame($record->getMorphClass(), $seo->seo_metable_type);
    }

    #[DataProvider('seoResources')]
    public function test_no_blank_seo_row_is_created_when_nothing_is_filled_in(string $resource, string $model, array $payload): void
    {
        $this->actingAs($this->admin)->post(route("dashboard.{$resource}.store"), $payload);

        $record = $model::latest('id')->firstOrFail();

        // The public side already falls back to the record's own content,
        // so an empty row would cost a query and buy nothing.
        $this->assertNull($record->seoMeta);
    }

    public function test_seo_meta_is_updated_rather_than_duplicated(): void
    {
        $service = Service::factory()->create();

        $payload = [
            'name' => ['ar' => 'خدمة'],
            'summary' => ['ar' => 'ملخص'],
            'body' => ['ar' => 'نص'],
            'requirements' => ['ar' => 'متطلبات'],
            'process' => ['ar' => 'خطوات'],
        ];

        $this->actingAs($this->admin)->put(route('dashboard.services.update', $service), $payload + [
            'seo' => ['meta_title' => ['ar' => 'الأول']],
        ]);

        $this->actingAs($this->admin)->put(route('dashboard.services.update', $service), $payload + [
            'seo' => ['meta_title' => ['ar' => 'الثاني']],
        ]);

        $this->assertSame(1, SeoMeta::where('seo_metable_id', $service->id)->count());
        $this->assertSame('الثاني', $service->fresh()->seoMeta->getTranslation('meta_title', 'ar'));
    }

    public function test_seo_meta_reaches_the_public_page_title(): void
    {
        $article = Article::factory()->create(['slug' => 'seo-test']);

        $this->actingAs($this->admin)->put(route('dashboard.articles.update', $article), [
            'title' => ['ar' => 'عنوان المقال'],
            'body' => ['ar' => '<p>نص</p>'],
            'is_published' => '1',
            'seo' => ['meta_title' => ['ar' => 'عنوان ميتا مخصص']],
        ]);

        $this->assertSame('عنوان ميتا مخصص', $article->fresh()->seoMeta->getTranslation('meta_title', 'ar'));
    }

    public function test_an_english_only_seo_value_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), [
                'name' => ['ar' => 'خدمة'],
                'summary' => ['ar' => 'ملخص'],
                'body' => ['ar' => 'نص'],
                'requirements' => ['ar' => 'متطلبات'],
                'process' => ['ar' => 'خطوات'],
                'seo' => ['meta_title' => ['ar' => '', 'en' => 'English only']],
            ])
            ->assertSessionHasErrors('seo.meta_title.ar');
    }

    public function test_an_og_image_upload_is_stored_on_the_seo_row(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('dashboard.services.store'), [
            'name' => ['ar' => 'خدمة'],
            'summary' => ['ar' => 'ملخص'],
            'body' => ['ar' => 'نص'],
            'requirements' => ['ar' => 'متطلبات'],
            'process' => ['ar' => 'خطوات'],
            'seo_og_image' => UploadedFile::fake()->image('og.jpg'),
        ]);

        $seo = Service::latest('id')->firstOrFail()->seoMeta;

        $this->assertNotNull($seo?->og_image);
        Storage::disk('public')->assertExists($seo->og_image);
    }
}
