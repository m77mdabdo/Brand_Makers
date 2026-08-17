<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * No dashboard screen may render a raw translation key.
 *
 * `__()` returns the key itself when it cannot resolve one, so a missing
 * entry does not throw, does not log, and does not fail any existing
 * test — it just prints "dashboard.resources.packages" in the sidebar and
 * as the page title, which is how that one survived being shipped.
 *
 * Two passes, because each catches what the other cannot. The static scan
 * reads every literal key in the source and checks it exists in both lang
 * files, so it covers screens no test visits. The render pass fetches the
 * real pages and looks for the pattern, so it catches keys built at
 * runtime that no static scan can see.
 */
class DashboardTranslationKeysTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every fully-literal dashboard.* key passed to a translation call.
     *
     * Deliberately NOT every 'dashboard.*' string in the codebase: route
     * names look identical and vastly outnumber them, and matching those
     * would report hundreds of "missing translations" for routes. Only
     * __(), @lang() and trans() count, and only when the key is a
     * complete literal — a concatenated prefix like 'dashboard.status.'
     * is resolved at runtime and belongs to the render pass below.
     *
     * @return list<string>
     */
    private function literalKeys(): array
    {
        $roots = [
            resource_path('views'),
            app_path('Http/Controllers/Dashboard'),
            app_path('Http/Requests/Dashboard'),
            app_path('Services'),
            app_path('Models'),
        ];

        $keys = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                preg_match_all(
                    "/(?:__|@lang|trans)\(\s*'(dashboard\.[A-Za-z0-9_.]+)'\s*[,)]/",
                    file_get_contents($file->getPathname()),
                    $matches
                );

                foreach ($matches[1] as $key) {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        return ['Arabic' => ['ar'], 'English' => ['en']];
    }

    #[DataProvider('locales')]
    public function test_every_dashboard_key_in_the_source_resolves(string $locale): void
    {
        $keys = $this->literalKeys();

        $this->assertGreaterThan(300, count($keys), 'The scanner found suspiciously few keys — check the pattern.');

        $missing = [];

        foreach ($keys as $key) {
            if (__($key, [], $locale) === $key) {
                $missing[] = $key;
            }
        }

        $this->assertSame([], $missing, "Unresolvable in {$locale}: ".implode(', ', $missing));
    }

    /**
     * The screens themselves, in both languages.
     *
     * A raw key is unmistakable in rendered output — a run of lowercase
     * words joined by dots, starting with "dashboard." — and cannot occur
     * legitimately in copy.
     */
    #[DataProvider('locales')]
    public function test_no_dashboard_screen_renders_a_raw_key(string $locale): void
    {
        $this->seed();

        $admin = User::where('is_admin', true)->firstOrFail();

        $this->app->setLocale($locale);

        /*
         * Every parameter-free dashboard GET route, discovered from the
         * router rather than listed here. A hardcoded list only ever
         * covers the screens someone remembered, and the whole failure
         * mode being guarded against is a screen nobody looked at.
         */
        $screens = collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($route, string $name) => str_starts_with($name, 'dashboard.')
                && in_array('GET', $route->methods(), true)
                && ! str_contains($route->uri(), '{'))
            ->keys();

        $this->assertGreaterThan(20, $screens->count(), 'Too few dashboard screens discovered.');

        foreach ($screens as $route) {
            $body = $this->actingAs($admin)->get(route($route))->assertOk()->getContent();

            preg_match_all('/dashboard(?:\.[a-z0-9_]+){2,}/', $body, $leaks);

            // Route names appear in hrefs and form actions legitimately;
            // what must not appear is a key rendered as visible TEXT.
            $visible = array_values(array_filter(
                array_unique($leaks[0]),
                fn (string $leak) => str_contains(strip_tags($body), $leak)
            ));

            $this->assertSame([], $visible, "Raw key(s) rendered on {$route} in {$locale}: ".implode(', ', $visible));
        }
    }
}
