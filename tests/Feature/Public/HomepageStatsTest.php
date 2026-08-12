<?php

namespace Tests\Feature\Public;

use App\Models\Setting;
use App\Models\User;
use App\Services\Cms\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The hero's three figures.
 *
 * The design reference carried "+500 companies / 48h / 98% satisfaction".
 * None of those is verified, so they are NOT seeded — the block is hidden
 * until an admin enters real numbers, and this is the test that stops
 * someone reinstating them as defaults later.
 */
class HomepageStatsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        return ['Arabic' => ['/'], 'English' => ['/en']];
    }

    #[DataProvider('locales')]
    public function test_no_stats_are_shown_on_a_fresh_install(string $url): void
    {
        $this->seed();

        $response = $this->get($url);

        $response->assertOk();

        foreach (['companies', 'turnaround', 'satisfaction'] as $key) {
            $response->assertDontSee(__('site.hero.stats.'.$key), false);
        }
    }

    public function test_the_seed_stores_the_stats_empty_rather_than_omitting_them(): void
    {
        $this->seed();

        // Present (so the editor lists them) but null (so nothing renders).
        foreach (['home_stat_companies', 'home_stat_turnaround', 'home_stat_satisfaction'] as $key) {
            $this->assertDatabaseHas('settings', ['key' => $key, 'group' => 'home_stats']);
            $this->assertNull(Setting::where('key', $key)->value('value'));
        }
    }

    #[DataProvider('locales')]
    public function test_stats_appear_once_configured_and_render_per_locale(string $url): void
    {
        $this->seed();
        $this->storeStats();

        $locale = $url === '/' ? 'ar' : 'en';
        $response = $this->get($url);

        $response->assertSee($locale === 'ar' ? '٥٠٠+' : '500+', false);
        $response->assertSee($locale === 'ar' ? '٤٨ ساعة' : '48 hours', false);
        $response->assertSee(__('site.hero.stats.companies', [], $locale), false);
    }

    public function test_only_the_configured_stats_are_shown(): void
    {
        $this->seed();

        // One figure entered, two left blank: the hero shows one stat, not
        // one stat and two empty slots beside it.
        $this->writeSetting('home_stat_companies', ['ar' => '٥٠٠+', 'en' => '500+']);

        $response = $this->get('/');

        $response->assertSee(__('site.hero.stats.companies'), false);
        $response->assertDontSee(__('site.hero.stats.turnaround'), false);
        $response->assertDontSee(__('site.hero.stats.satisfaction'), false);
    }

    public function test_clearing_a_stat_hides_it_again(): void
    {
        $this->seed();
        $this->storeStats();
        $this->get('/')->assertSee(__('site.hero.stats.companies'), false);

        $this->writeSetting('home_stat_companies', null);

        $this->get('/')->assertDontSee(__('site.hero.stats.companies'), false);
    }

    public function test_an_admin_can_set_them_through_the_settings_editor(): void
    {
        $this->seed();
        $admin = User::factory()->admin()->create();

        $payload = ['settings' => [
            'site_name' => ['ar' => 'صنّاع العلامات التجارية', 'en' => 'Creative Mark'],
            'home_stat_companies' => ['ar' => '١٢٠+', 'en' => '120+'],
        ]];

        $this->actingAs($admin)->put(route('dashboard.settings.update'), $payload)->assertRedirect();

        $this->get('/')->assertSee('١٢٠+', false);
        $this->get('/en')->assertSee('120+', false);
    }

    private function storeStats(): void
    {
        $this->writeSetting('home_stat_companies', ['ar' => '٥٠٠+', 'en' => '500+']);
        $this->writeSetting('home_stat_turnaround', ['ar' => '٤٨ ساعة', 'en' => '48 hours']);
        $this->writeSetting('home_stat_satisfaction', ['ar' => '٩٨٪', 'en' => '98%']);
    }

    private function writeSetting(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'home_stats']);

        // The repository is a per-request singleton, so a write made
        // outside a request has to invalidate it explicitly.
        app(SettingsRepository::class)->flush();
    }
}
