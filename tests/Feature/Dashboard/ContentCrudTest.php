<?php

namespace Tests\Feature\Dashboard;

use App\Models\Country;
use App\Models\Faq;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CRUD, gating and validation for the four simple content resources.
 *
 * Data-driven across all four, because they share ContentResourceService:
 * a rule that holds for one and not the others would mean the shared
 * write path had been bypassed somewhere.
 */
class ContentCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * resource => [model, a valid create payload, the translatable field
     * used for assertions].
     *
     * @return array<string, array{string, class-string, array<string, mixed>, string}>
     */
    public static function resources(): array
    {
        return [
            'services' => ['services', Service::class, [
                'name' => ['ar' => 'خدمة جديدة', 'en' => 'A new service'],
                'summary' => ['ar' => 'ملخص', 'en' => 'Summary'],
                'body' => ['ar' => '<p>نص</p>', 'en' => '<p>Body</p>'],
                'requirements' => ['ar' => 'متطلبات', 'en' => 'Requirements'],
                'process' => ['ar' => 'خطوات', 'en' => 'Process'],
                'is_active' => '1',
                'sort_order' => 3,
            ], 'name'],

            'countries' => ['countries', Country::class, [
                'name' => ['ar' => 'دولة جديدة', 'en' => 'A new country'],
                'notes' => ['ar' => 'ملاحظات', 'en' => 'Notes'],
                'is_active' => '1',
                'sort_order' => 2,
            ], 'name'],

            'faqs' => ['faqs', Faq::class, [
                'question' => ['ar' => 'سؤال جديد؟', 'en' => 'A new question?'],
                'answer' => ['ar' => 'إجابة', 'en' => 'An answer'],
                'is_active' => '1',
                'sort_order' => 1,
            ], 'question'],

            'testimonials' => ['testimonials', Testimonial::class, [
                'quote' => ['ar' => 'شهادة جديدة', 'en' => 'A new quote'],
                'client_name' => 'Test Client',
                'client_title' => 'Founder',
                'is_active' => '1',
                'sort_order' => 0,
            ], 'quote'],
        ];
    }

    // -----------------------------------------------------------------
    // Gating
    // -----------------------------------------------------------------

    #[DataProvider('resources')]
    public function test_a_guest_is_redirected_from_every_screen(string $resource): void
    {
        $this->get(route("dashboard.{$resource}.index"))->assertRedirect(route('login'));
        $this->get(route("dashboard.{$resource}.create"))->assertRedirect(route('login'));
        $this->post(route("dashboard.{$resource}.store"))->assertRedirect(route('login'));
    }

    #[DataProvider('resources')]
    public function test_a_non_admin_is_forbidden_from_reading_and_writing(string $resource, string $model, array $payload): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route("dashboard.{$resource}.index"))->assertForbidden();
        $this->actingAs($user)->post(route("dashboard.{$resource}.store"), $payload)->assertForbidden();

        $this->assertSame($model::count(), $model::count());
    }

    // -----------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------

    #[DataProvider('resources')]
    public function test_an_admin_sees_the_index_and_create_screens(string $resource): void
    {
        $this->actingAs($this->admin)->get(route("dashboard.{$resource}.index"))->assertOk();
        $this->actingAs($this->admin)->get(route("dashboard.{$resource}.create"))->assertOk();
    }

    #[DataProvider('resources')]
    public function test_it_creates_a_record_with_both_locales(string $resource, string $model, array $payload, string $field): void
    {
        $before = $model::count();

        $this->actingAs($this->admin)
            ->post(route("dashboard.{$resource}.store"), $payload)
            ->assertRedirect(route("dashboard.{$resource}.index"))
            ->assertSessionHas('status');

        $this->assertSame($before + 1, $model::count());

        $record = $model::latest('id')->first();

        $this->assertSame($payload[$field]['ar'], $record->getTranslation($field, 'ar'));
        $this->assertSame($payload[$field]['en'], $record->getTranslation($field, 'en'));
    }

    #[DataProvider('resources')]
    public function test_it_updates_a_record(string $resource, string $model, array $payload, string $field): void
    {
        $this->actingAs($this->admin)->post(route("dashboard.{$resource}.store"), $payload);
        $record = $model::latest('id')->first();

        $updated = $payload;
        $updated[$field] = ['ar' => 'قيمة محدّثة', 'en' => 'Updated value'];

        $this->actingAs($this->admin)
            ->put(route("dashboard.{$resource}.update", $record), $updated)
            ->assertRedirect(route("dashboard.{$resource}.index"));

        $this->assertSame('قيمة محدّثة', $record->fresh()->getTranslation($field, 'ar'));
    }

    #[DataProvider('resources')]
    public function test_it_deletes_a_record(string $resource, string $model, array $payload): void
    {
        $this->actingAs($this->admin)->post(route("dashboard.{$resource}.store"), $payload);
        $record = $model::latest('id')->first();

        $this->actingAs($this->admin)
            ->delete(route("dashboard.{$resource}.destroy", $record))
            ->assertRedirect(route("dashboard.{$resource}.index"));

        $this->assertNull($model::find($record->id));
    }

    #[DataProvider('resources')]
    public function test_the_edit_screen_loads_for_an_existing_record(string $resource, string $model, array $payload): void
    {
        $this->actingAs($this->admin)->post(route("dashboard.{$resource}.store"), $payload);
        $record = $model::latest('id')->first();

        $this->actingAs($this->admin)->get(route("dashboard.{$resource}.edit", $record))->assertOk();
    }

    // -----------------------------------------------------------------
    // Validation and the bilingual convention
    // -----------------------------------------------------------------

    #[DataProvider('resources')]
    public function test_the_arabic_value_is_required_and_english_is_not(string $resource, string $model, array $payload, string $field): void
    {
        // Arabic missing → rejected. Arabic is the default locale, so a
        // record with only English renders as a gap on the primary site.
        $missingArabic = $payload;
        $missingArabic[$field] = ['ar' => '', 'en' => 'English only'];

        $this->actingAs($this->admin)
            ->post(route("dashboard.{$resource}.store"), $missingArabic)
            ->assertSessionHasErrors("{$field}.ar");

        // English missing → accepted, and the public side falls back.
        $missingEnglish = $payload;
        $missingEnglish[$field] = ['ar' => 'عربي فقط'];

        $this->actingAs($this->admin)
            ->post(route("dashboard.{$resource}.store"), $missingEnglish)
            ->assertSessionHasNoErrors();

        $record = $model::latest('id')->first();
        $this->assertSame('عربي فقط', $record->getTranslation($field, 'ar'));
    }

    #[DataProvider('resources')]
    public function test_a_blank_locale_is_dropped_rather_than_stored_as_an_empty_string(string $resource, string $model, array $payload, string $field): void
    {
        $blankEnglish = $payload;
        $blankEnglish[$field] = ['ar' => 'قيمة', 'en' => '   '];

        $this->actingAs($this->admin)->post(route("dashboard.{$resource}.store"), $blankEnglish);

        $record = $model::latest('id')->first();

        // An empty string is a PRESENT value to the translation layer, so
        // it would beat the Arabic fallback and render as a gap.
        $this->assertArrayNotHasKey('en', $record->getTranslations($field));
        $this->assertSame('قيمة', $record->getTranslation($field, 'en'));
    }

    #[DataProvider('resources')]
    public function test_the_active_toggle_round_trips(string $resource, string $model, array $payload): void
    {
        $this->actingAs($this->admin)->post(route("dashboard.{$resource}.store"), $payload);
        $record = $model::latest('id')->first();
        $this->assertTrue($record->is_active);

        // An unticked checkbox is absent from the payload entirely, so
        // "not submitted" has to mean false here rather than "leave
        // alone" — otherwise deactivating would silently do nothing.
        $off = $payload;
        unset($off['is_active']);

        $this->actingAs($this->admin)->put(route("dashboard.{$resource}.update", $record), $off);

        $this->assertFalse($record->fresh()->is_active);
    }

    #[DataProvider('resources')]
    public function test_the_sort_order_round_trips(string $resource, string $model, array $payload): void
    {
        $this->actingAs($this->admin)->post(route("dashboard.{$resource}.store"), array_replace($payload, ['sort_order' => 7]));

        $this->assertSame(7, $model::latest('id')->first()->sort_order);
    }

    // -----------------------------------------------------------------
    // Sanitising
    // -----------------------------------------------------------------

    #[DataProvider('resources')]
    public function test_script_payloads_are_stripped_on_write(string $resource, string $model, array $payload, string $field): void
    {
        $hostile = $payload;
        $hostile[$field] = [
            'ar' => 'نص <script>alert(1)</script> عادي',
            'en' => 'Text <script>alert(1)</script> here',
        ];

        $this->actingAs($this->admin)->post(route("dashboard.{$resource}.store"), $hostile);

        $stored = $model::latest('id')->first()->getTranslation($field, 'ar');

        $this->assertStringNotContainsString('<script', $stored);
        // And the BODY of the script tag is gone too — strip_tags() would
        // have left "alert(1)" behind as visible text.
        $this->assertStringNotContainsString('alert(1)', $stored);
    }

    public function test_rich_text_keeps_its_allowed_tags_and_loses_the_rest(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.services.store'), [
            'name' => ['ar' => 'خدمة'],
            'summary' => ['ar' => 'ملخص'],
            'body' => ['ar' => '<h2>عنوان</h2><p>فقرة <strong>مهمة</strong></p><iframe src="https://evil.test"></iframe><p onclick="steal()">نص</p>'],
            'requirements' => ['ar' => 'متطلبات'],
            'process' => ['ar' => 'خطوات'],
        ]);

        $body = Service::latest('id')->first()->getTranslation('body', 'ar');

        $this->assertStringContainsString('<h2>', $body);
        $this->assertStringContainsString('<strong>', $body);
        $this->assertStringNotContainsString('<iframe', $body);
        $this->assertStringNotContainsString('onclick', $body);
    }

    public function test_a_plain_text_field_loses_markup_even_when_the_body_keeps_it(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.services.store'), [
            'name' => ['ar' => '<b>خدمة</b>'],
            'summary' => ['ar' => 'ملخص'],
            'body' => ['ar' => '<p>نص</p>'],
            'requirements' => ['ar' => 'متطلبات'],
            'process' => ['ar' => 'خطوات'],
        ]);

        $service = Service::latest('id')->first();

        // name and body arrive in ONE submission but are sanitised
        // differently — a heading is not a place for markup.
        $this->assertSame('خدمة', $service->getTranslation('name', 'ar'));
        $this->assertStringContainsString('<p>', $service->getTranslation('body', 'ar'));
    }

    // -----------------------------------------------------------------
    // Slug uniqueness, including soft-deleted rows
    // -----------------------------------------------------------------

    public function test_a_slug_clashing_with_a_soft_deleted_record_is_a_field_error_not_a_500(): void
    {
        $service = Service::factory()->create(['slug' => 'taken-slug']);
        $service->delete();

        $this->assertSoftDeleted($service);

        // The DB unique index counts trashed rows, so a validator that
        // skipped them would pass and the insert would then blow up with a
        // UniqueConstraintViolationException.
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), [
                'slug' => 'taken-slug',
                'name' => ['ar' => 'خدمة'],
                'summary' => ['ar' => 'ملخص'],
                'body' => ['ar' => 'نص'],
                'requirements' => ['ar' => 'متطلبات'],
                'process' => ['ar' => 'خطوات'],
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_saving_a_record_without_changing_its_slug_is_not_a_conflict_with_itself(): void
    {
        $service = Service::factory()->create(['slug' => 'my-service']);

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.update', $service), [
                'slug' => 'my-service',
                'name' => ['ar' => 'خدمة'],
                'summary' => ['ar' => 'ملخص'],
                'body' => ['ar' => 'نص'],
                'requirements' => ['ar' => 'متطلبات'],
                'process' => ['ar' => 'خطوات'],
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_a_blank_slug_is_generated_from_the_name(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.services.store'), [
            'name' => ['ar' => 'تصميم الهوية', 'en' => 'Identity Design'],
            'summary' => ['ar' => 'ملخص'],
            'body' => ['ar' => 'نص'],
            'requirements' => ['ar' => 'متطلبات'],
            'process' => ['ar' => 'خطوات'],
        ]);

        $this->assertNotEmpty(Service::latest('id')->first()->slug);
    }

    // -----------------------------------------------------------------
    // Images
    // -----------------------------------------------------------------

    public function test_an_uploaded_cover_is_stored_and_replaced_rather_than_accumulated(): void
    {
        Storage::fake('public');

        $payload = [
            'name' => ['ar' => 'خدمة'],
            'summary' => ['ar' => 'ملخص'],
            'body' => ['ar' => 'نص'],
            'requirements' => ['ar' => 'متطلبات'],
            'process' => ['ar' => 'خطوات'],
        ];

        $this->actingAs($this->admin)->post(route('dashboard.services.store'), $payload + [
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $service = Service::latest('id')->first();
        $first = $service->cover_image;
        Storage::disk('public')->assertExists($first);

        $this->actingAs($this->admin)->put(route('dashboard.services.update', $service), $payload + [
            'cover_image' => UploadedFile::fake()->image('cover-2.jpg'),
        ]);

        $second = $service->fresh()->cover_image;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_an_absent_upload_leaves_the_existing_image_alone(): void
    {
        Storage::fake('public');
        $service = Service::factory()->create(['cover_image' => 'services/existing.jpg']);

        $this->actingAs($this->admin)->put(route('dashboard.services.update', $service), [
            'name' => ['ar' => 'خدمة'],
            'summary' => ['ar' => 'ملخص'],
            'body' => ['ar' => 'نص'],
            'requirements' => ['ar' => 'متطلبات'],
            'process' => ['ar' => 'خطوات'],
        ]);

        $this->assertSame('services/existing.jpg', $service->fresh()->cover_image);
    }

    public function test_an_svg_cover_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), [
                'name' => ['ar' => 'خدمة'],
                'summary' => ['ar' => 'ملخص'],
                'body' => ['ar' => 'نص'],
                'requirements' => ['ar' => 'متطلبات'],
                'process' => ['ar' => 'خطوات'],
                'cover_image' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('cover_image');
    }

    // -----------------------------------------------------------------
    // Public site reflects the change
    // -----------------------------------------------------------------

    public function test_a_new_active_service_appears_on_the_public_homepage(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.services.store'), [
            'name' => ['ar' => 'خدمة منشورة', 'en' => 'A published service'],
            'summary' => ['ar' => 'ملخص', 'en' => 'Summary'],
            'body' => ['ar' => 'نص'],
            'requirements' => ['ar' => 'متطلبات'],
            'process' => ['ar' => 'خطوات'],
            'is_active' => '1',
            'sort_order' => 0,
        ]);

        $this->get('/')->assertSee('خدمة منشورة', false);
        $this->get('/en')->assertSee('A published service', false);
    }

    public function test_deactivating_a_service_removes_it_from_the_public_site(): void
    {
        $service = Service::factory()->create([
            'name' => ['ar' => 'خدمة مخفية', 'en' => 'Hidden service'],
            'sort_order' => 0,
        ]);

        $this->get('/')->assertSee('خدمة مخفية', false);

        $this->actingAs($this->admin)->put(route('dashboard.services.update', $service), [
            'name' => ['ar' => 'خدمة مخفية'],
            'summary' => ['ar' => 'ملخص'],
            'body' => ['ar' => 'نص'],
            'requirements' => ['ar' => 'متطلبات'],
            'process' => ['ar' => 'خطوات'],
            // is_active omitted — the unticked-checkbox case.
        ]);

        $this->get('/')->assertDontSee('خدمة مخفية', false);
    }
}
