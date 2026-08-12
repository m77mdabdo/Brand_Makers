<?php

namespace Tests\Feature\Dashboard;

use App\Models\Setting;
use App\Models\User;
use App\Services\Cms\SettingsRepository;
use App\Services\Cms\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsManagerTest extends TestCase
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
     * A complete, valid payload for every editable key, so a test can
     * change one field without every other one being treated as cleared.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $settings = [];

        foreach (SettingsService::keys() as $key => $type) {
            $settings[$key] = match ($type) {
                SettingsService::TYPE_IMAGE => null,
                SettingsService::TYPE_TRANSLATED,
                SettingsService::TYPE_TRANSLATED_AREA => ['ar' => 'قيمة', 'en' => 'Value'],
                SettingsService::TYPE_EMAIL => 'hello@example.test',
                SettingsService::TYPE_PHONE => '+966500000000',
                SettingsService::TYPE_URL => 'https://example.test/page',
                SettingsService::TYPE_INTEGER => 30,
                default => 'value',
            };
        }

        return ['settings' => array_replace($settings, $overrides)];
    }

    // -----------------------------------------------------------------
    // Persistence
    // -----------------------------------------------------------------

    public function test_the_editor_renders_every_group_and_field(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.settings.edit'));

        $response->assertOk();

        foreach (array_keys(SettingsService::DEFINITION) as $group) {
            $response->assertSee(__('dashboard.settings.groups.'.$group), false);
        }

        foreach (array_keys(SettingsService::keys()) as $key) {
            $response->assertSee(__('dashboard.settings.fields.'.$key), false);
        }
    }

    public function test_it_persists_a_submitted_value(): void
    {
        $this->actingAs($this->admin)
            ->put(route('dashboard.settings.update'), $this->payload([
                'contact_email' => 'studio@creativemark.test',
                'contact_phone' => '+966 55 123 4567',
            ]))
            ->assertRedirect(route('dashboard.settings.edit'))
            ->assertSessionHas('status');

        $settings = app(SettingsRepository::class);
        $settings->flush();

        $this->assertSame('studio@creativemark.test', $settings->string('contact_email'));
        $this->assertSame('+966 55 123 4567', $settings->string('contact_phone'));
    }

    public function test_it_persists_both_locales_of_a_translated_value(): void
    {
        $this->actingAs($this->admin)->put(route('dashboard.settings.update'), $this->payload([
            'site_tagline' => ['ar' => 'نبني العلامات', 'en' => 'We build brands'],
        ]));

        $stored = Setting::where('key', 'site_tagline')->value('value');

        $this->assertSame(['ar' => 'نبني العلامات', 'en' => 'We build brands'], $stored);
    }

    public function test_a_cleared_field_is_stored_as_null_not_as_an_empty_string(): void
    {
        Setting::updateOrCreate(['key' => 'contact_email'], ['value' => 'old@example.test', 'group' => 'contact']);

        $this->actingAs($this->admin)->put(route('dashboard.settings.update'), $this->payload([
            'contact_email' => '',
        ]));

        // "never set" and "cleared" must be the same state: every consumer
        // tests for a truthy value, so a stored "" would render an empty
        // row in the footer rather than hiding it.
        $this->assertNull(Setting::where('key', 'contact_email')->value('value'));
    }

    public function test_saving_settings_does_not_change_how_many_rows_exist(): void
    {
        $before = Setting::count();

        $this->actingAs($this->admin)->put(route('dashboard.settings.update'), $this->payload());

        $this->assertSame($before, Setting::count());
    }

    // -----------------------------------------------------------------
    // Arbitrary keys — the point of the definition-driven write
    // -----------------------------------------------------------------

    public function test_a_key_outside_the_definition_is_never_written(): void
    {
        $this->actingAs($this->admin)->put(route('dashboard.settings.update'), $this->payload([
            'app_key' => 'base64:pwned',
            'visitor_sessions_enabled' => false,
            'arbitrary_nonsense' => 'x',
            'home_stat_companies; DROP TABLE settings;--' => 'x',
        ]));

        foreach (['app_key', 'arbitrary_nonsense', 'home_stat_companies; DROP TABLE settings;--'] as $key) {
            $this->assertDatabaseMissing('settings', ['key' => $key]);
        }

        // The settings table itself is intact.
        $this->assertGreaterThan(0, Setting::count());
    }

    public function test_an_unknown_key_is_dropped_by_validation_before_it_reaches_the_service(): void
    {
        $this->actingAs($this->admin)->put(route('dashboard.settings.update'), $this->payload([
            'not_a_setting' => 'value',
        ]));

        $this->assertDatabaseMissing('settings', ['key' => 'not_a_setting']);
    }

    /**
     * The second, independent layer. Even calling the service directly —
     * bypassing the Form Request entirely — an unknown key cannot be
     * written, because update() iterates the DEFINITION and pulls values
     * out of the payload rather than iterating the payload.
     */
    public function test_the_service_itself_ignores_unknown_keys_even_without_validation(): void
    {
        app(SettingsService::class)->update([
            'app_key' => 'base64:pwned',
            'contact_email' => 'real@example.test',
        ]);

        $this->assertDatabaseMissing('settings', ['key' => 'app_key']);
        $this->assertSame('real@example.test', Setting::where('key', 'contact_email')->value('value'));
    }

    public function test_invalid_values_are_rejected_with_errors(): void
    {
        $this->actingAs($this->admin)
            ->put(route('dashboard.settings.update'), $this->payload([
                'contact_email' => 'not-an-email',
                'social_instagram' => 'javascript:alert(1)',
            ]))
            ->assertSessionHasErrors(['settings.contact_email', 'settings.social_instagram']);
    }

    public function test_an_english_only_translated_value_is_rejected(): void
    {
        // Arabic is the default locale, so a value with English but no
        // Arabic would render as a gap on the primary site.
        $this->actingAs($this->admin)
            ->put(route('dashboard.settings.update'), $this->payload([
                'site_tagline' => ['ar' => '', 'en' => 'English only'],
            ]))
            ->assertSessionHasErrors('settings.site_tagline.ar');
    }

    // -----------------------------------------------------------------
    // Logo upload
    // -----------------------------------------------------------------

    public function test_the_logo_uploads_and_replaces_the_previous_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->put(
            route('dashboard.settings.update'),
            $this->payload() + ['site_logo' => UploadedFile::fake()->image('logo.png')]
        );

        $first = Setting::where('key', 'site_logo')->value('value');
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        $this->actingAs($this->admin)->put(
            route('dashboard.settings.update'),
            $this->payload() + ['site_logo' => UploadedFile::fake()->image('logo-2.png')]
        );

        $second = Setting::where('key', 'site_logo')->value('value');
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_an_absent_upload_leaves_the_current_logo_alone(): void
    {
        Storage::fake('public');
        Setting::updateOrCreate(['key' => 'site_logo'], ['value' => 'settings/existing.png', 'group' => 'general']);

        $this->actingAs($this->admin)->put(route('dashboard.settings.update'), $this->payload());

        $this->assertSame('settings/existing.png', Setting::where('key', 'site_logo')->value('value'));
    }

    public function test_an_svg_logo_is_rejected(): void
    {
        Storage::fake('public');

        // An SVG is an executable document served from this origin, and
        // there is no sanitiser in the project.
        $this->actingAs($this->admin)
            ->put(route('dashboard.settings.update'), $this->payload() + [
                'site_logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('site_logo');
    }

    // -----------------------------------------------------------------
    // The public site reflects the change
    // -----------------------------------------------------------------

    public function test_a_saved_setting_re_renders_on_the_public_site(): void
    {
        $this->actingAs($this->admin)->put(route('dashboard.settings.update'), $this->payload([
            'contact_email' => 'studio@creativemark.test',
            'social_instagram' => 'https://instagram.com/creativemark',
            'site_name' => ['ar' => 'اسم جديد', 'en' => 'A New Name'],
        ]));

        $this->get('/')
            ->assertSee('studio@creativemark.test', false)
            ->assertSee('https://instagram.com/creativemark', false)
            ->assertSee('اسم جديد', false);

        $this->get('/en')->assertSee('A New Name', false);
    }

    public function test_an_unconfigured_contact_block_renders_nothing_rather_than_a_blank_heading(): void
    {
        // The production seed ships the contact details empty on purpose,
        // so the heading must not render either — a "Contact" heading with
        // nothing under it reads as a broken page.
        //
        // Scoped to the FOOTER rather than the whole document: the Arabic
        // heading is a substring of copy that legitimately appears
        // elsewhere on the homepage (the estimator's "تواصل معنا…" hint),
        // so a page-wide assertDontSee would fail on unrelated text.
        $this->assertStringNotContainsString(
            __('site.footer.contact_heading', [], 'ar'),
            $this->footerOf($this->get('/')->getContent()),
        );

        $this->actingAs($this->admin)->put(route('dashboard.settings.update'), $this->payload([
            'contact_email' => 'studio@creativemark.test',
        ]));

        // …and appears as soon as there is something to put under it.
        $this->assertStringContainsString(
            __('site.footer.contact_heading', [], 'ar'),
            $this->footerOf($this->get('/')->getContent()),
        );
    }

    private function footerOf(string $html): string
    {
        preg_match('/<footer\b.*?<\/footer>/s', $html, $matches);

        $this->assertNotEmpty($matches, 'The page rendered no footer at all.');

        return $matches[0];
    }

    public function test_the_repository_reads_the_table_once_per_request(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->flush();

        DB::enableQueryLog();

        $settings->string('contact_email');
        $settings->string('contact_phone');
        $settings->translated('site_name');
        $settings->socialLinks();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Before the repository each caller ran its own lookup; the public
        // layout alone would have issued a dozen identical queries.
        $this->assertCount(1, $queries);
    }
}
