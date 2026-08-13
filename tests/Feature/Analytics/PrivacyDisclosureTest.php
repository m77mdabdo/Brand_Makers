<?php

namespace Tests\Feature\Analytics;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Services\Cms\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The disclosure, and the settings that have to stay in step with it.
 */
class PrivacyDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private const MARKERS = [
        'ar' => '<h2>إشارات الخصوصية في المتصفح</h2>',
        'en' => '<h2>Browser privacy signals</h2>',
    ];

    private function migration(): object
    {
        return require database_path('migrations/2026_08_14_000100_add_browser_privacy_signals_disclosure.php');
    }

    // -----------------------------------------------------------------
    // The published policy
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     */
    public static function locales(): array
    {
        return [
            'Arabic' => ['ar', '/privacy-policy'],
            'English' => ['en', '/en/privacy-policy'],
        ];
    }

    #[DataProvider('locales')]
    public function test_the_published_policy_discloses_the_browser_privacy_signals(string $locale, string $url): void
    {
        $this->seed();

        // The whole point of the section: a visitor can read that DNT/GPC
        // overrides consent, before deciding whether to grant it.
        $this->get($url)->assertSee(self::MARKERS[$locale], false);
    }

    #[DataProvider('locales')]
    public function test_the_published_policy_still_discloses_the_truncated_ip(string $locale, string $url): void
    {
        $this->seed();

        $needle = $locale === 'ar' ? 'لا نسجّل عنوان IP كاملاً' : 'We never store a full IP address';

        $this->get($url)->assertSee($needle, false);
    }

    /**
     * The migration and the seeder ship the SAME copy — the migration's
     * idempotency check depends on it, so a fresh install that seeded the
     * section is not given a second one.
     */
    public function test_the_migration_and_the_seeder_have_not_drifted(): void
    {
        $seeder = File::get(database_path('seeders/PageContentSeeder.php'));

        foreach (self::MARKERS as $marker) {
            $this->assertStringContainsString($marker, $seeder);
        }

        $this->seed();

        // The real proof: running the migration against a freshly seeded
        // install adds nothing.
        $before = Page::where('slug', 'privacy-policy')->sole()->getTranslations('body');

        $this->migration()->up();

        $this->assertSame($before, Page::where('slug', 'privacy-policy')->sole()->getTranslations('body'));
    }

    // -----------------------------------------------------------------
    // The data migration, for installs that predate the disclosure
    // -----------------------------------------------------------------

    private function policyWithout(): Page
    {
        return Page::create([
            'slug' => 'privacy-policy',
            'title' => ['ar' => 'سياسة الخصوصية', 'en' => 'Privacy Policy'],
            'body' => [
                'ar' => '<h2>ما الذي نجمعه</h2><p>نص كتبه المسؤول بنفسه.</p>',
                'en' => '<h2>What we collect</h2><p>Wording the admin refined by hand.</p>',
            ],
            'meta_title' => ['ar' => 'سياسة الخصوصية', 'en' => 'Privacy Policy'],
            'meta_description' => ['ar' => 'وصف', 'en' => 'Description'],
        ]);
    }

    public function test_it_appends_the_section_without_destroying_existing_wording(): void
    {
        $page = $this->policyWithout();

        $this->migration()->up();
        $page->refresh();

        foreach (self::MARKERS as $locale => $marker) {
            $this->assertStringContainsString($marker, $page->getTranslation('body', $locale, false));
        }

        // The admin's own wording survives, which is the entire reason
        // this is a migration rather than a reseed.
        $this->assertStringContainsString('نص كتبه المسؤول بنفسه.', $page->getTranslation('body', 'ar', false));
        $this->assertStringContainsString('Wording the admin refined by hand.', $page->getTranslation('body', 'en', false));
    }

    public function test_running_it_twice_does_not_duplicate_the_section(): void
    {
        $page = $this->policyWithout();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, substr_count($page->fresh()->getTranslation('body', 'ar', false), self::MARKERS['ar']));
    }

    public function test_down_removes_exactly_what_up_added(): void
    {
        $page = $this->policyWithout();
        $original = $page->getTranslations('body');

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame($original, $page->fresh()->getTranslations('body'));
    }

    public function test_an_untranslated_locale_stays_untranslated(): void
    {
        // Appending an English-only body to a page that has no English
        // version would make /en/privacy-policy render a page that is
        // otherwise Arabic.
        $page = Page::create([
            'slug' => 'privacy-policy',
            'title' => ['ar' => 'سياسة الخصوصية'],
            'body' => ['ar' => '<p>عربية فقط.</p>'],
            'meta_title' => ['ar' => 'سياسة الخصوصية'],
            'meta_description' => ['ar' => 'وصف'],
        ]);

        $this->migration()->up();

        $this->assertStringContainsString(self::MARKERS['ar'], $page->fresh()->getTranslation('body', 'ar', false));
        $this->assertEmpty($page->fresh()->getTranslation('body', 'en', false));
    }

    public function test_it_is_a_no_op_on_a_database_with_no_policy_page_yet(): void
    {
        $this->assertSame(0, Page::where('slug', 'privacy-policy')->count());

        // migrate:fresh runs migrations BEFORE seeders, so this is the
        // ordinary case rather than an edge one.
        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(0, Page::where('slug', 'privacy-policy')->count());
    }

    // -----------------------------------------------------------------
    // The settings the policy promises are actually editable
    // -----------------------------------------------------------------

    public function test_both_visitor_session_settings_are_in_the_definition(): void
    {
        // Seeded but not editable would mean the retention window named in
        // the privacy policy could only be changed by a developer.
        $keys = SettingsService::keys();

        $this->assertSame(SettingsService::TYPE_BOOLEAN, $keys['visitor_sessions_enabled'] ?? null);
        $this->assertSame(SettingsService::TYPE_INTEGER, $keys['visitor_sessions_retention_days'] ?? null);
    }

    public function test_an_admin_can_change_the_retention_window_and_the_switch(): void
    {
        $this->seed();
        $admin = User::factory()->admin()->create();

        $payload = [];

        foreach (SettingsService::keys() as $key => $type) {
            $payload[$key] = match ($type) {
                SettingsService::TYPE_IMAGE => null,
                SettingsService::TYPE_TRANSLATED,
                SettingsService::TYPE_TRANSLATED_AREA => ['ar' => 'قيمة', 'en' => 'Value'],
                SettingsService::TYPE_EMAIL => 'hello@example.test',
                SettingsService::TYPE_PHONE => '+966500000000',
                SettingsService::TYPE_URL => 'https://example.test/page',
                SettingsService::TYPE_INTEGER => 45,
                SettingsService::TYPE_BOOLEAN => '0',
                default => 'value',
            };
        }

        $this->actingAs($admin)
            ->put(route('dashboard.settings.update'), ['settings' => $payload])
            ->assertRedirect();

        $this->assertFalse(Setting::where('key', 'visitor_sessions_enabled')->sole()->value);
        $this->assertSame(45, Setting::where('key', 'visitor_sessions_retention_days')->sole()->value);
    }

    public function test_the_editor_renders_the_switch_as_a_checkbox_and_the_window_as_a_number(): void
    {
        $this->seed();

        $content = $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard.settings.edit'))
            ->getContent();

        $this->assertStringContainsString('name="settings[visitor_sessions_enabled]"', $content);
        $this->assertStringContainsString('type="checkbox"', $content);
        // Rendered with its stored value rather than blank: string()
        // folds a non-string to null, so an integer read through it would
        // render empty and save as null on the next submit.
        $this->assertStringContainsString('value="90"', $content);
    }
}
