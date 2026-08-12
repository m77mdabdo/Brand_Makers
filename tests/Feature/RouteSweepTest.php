<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Walks every GET route the app registers and asserts none of them error,
 * then scans every view for route() names that no longer resolve.
 *
 * THE TRAP THIS KILLS. A sidebar or navbar link pointing at a route name
 * that a refactor removed throws RouteNotFoundException on route() — and
 * because that link renders in a shared layout, it takes down EVERY page
 * that uses the layout, not just the section that changed. A
 * section-by-section suite does not necessarily notice, because each
 * section's own tests may never render the sidebar in the state that
 * breaks.
 *
 * The same applies in reverse: removing a "coming soon" placeholder route
 * orphans whatever linked to it. Both directions are covered here.
 */
class RouteSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * @return array<int, RoutingRoute>
     */
    private function gettableRoutes(): array
    {
        return collect(Route::getRoutes())
            ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true))
            // Routes with required parameters need a real record; those
            // are covered by their own section tests.
            ->reject(fn (RoutingRoute $route) => str_contains($route->uri(), '{'))
            ->reject(fn (RoutingRoute $route) => str_starts_with($route->uri(), '_'))
            ->reject(fn (RoutingRoute $route) => in_array($route->uri(), ['up', 'sanctum/csrf-cookie'], true))
            ->values()
            ->all();
    }

    /**
     * Guards the guard. If the filters above ever narrowed to nothing,
     * every sweep below would pass vacuously and prove nothing.
     */
    public function test_the_sweep_actually_covers_both_locales_and_the_dashboard(): void
    {
        $uris = collect($this->gettableRoutes())->map->uri();

        $this->assertGreaterThan(4, $uris->reject(fn ($uri) => str_starts_with($uri, 'dashboard') || str_starts_with($uri, 'en'))->count());
        $this->assertGreaterThan(3, $uris->filter(fn ($uri) => str_starts_with($uri, 'en'))->count(), 'English routes missing.');
        $this->assertGreaterThan(10, $uris->filter(fn ($uri) => str_starts_with($uri, 'dashboard'))->count());

        // The sections built in this step specifically.
        foreach (['dashboard/services', 'dashboard/comments', 'dashboard/media', 'dashboard/menu', 'dashboard/tracking'] as $uri) {
            $this->assertTrue($uris->contains($uri), "Missing from the sweep: {$uri}");
        }
    }

    public function test_every_public_get_route_responds_without_erroring(): void
    {
        $failures = [];

        foreach ($this->gettableRoutes() as $route) {
            if (str_starts_with($route->uri(), 'dashboard')) {
                continue;
            }

            $status = $this->get('/'.ltrim($route->uri(), '/'))->getStatusCode();

            // An auth screen may redirect; that is fine. A 500 is not.
            if ($status >= 500) {
                $failures[] = ($route->getName() ?? $route->uri())." ({$route->uri()}) → {$status}";
            }
        }

        $this->assertSame([], $failures, 'Public routes erroring: '.implode(', ', $failures));
    }

    public function test_every_dashboard_get_route_returns_200_for_an_admin_in_both_locales(): void
    {
        $failures = [];

        foreach (['ar', 'en'] as $locale) {
            $admin = User::factory()->admin()->create(['locale' => $locale]);

            foreach ($this->gettableRoutes() as $route) {
                if (! str_starts_with($route->uri(), 'dashboard')) {
                    continue;
                }

                $status = $this->actingAs($admin)->get('/'.$route->uri())->getStatusCode();

                if ($status !== 200) {
                    $failures[] = "[{$locale}] {$route->getName()} ({$route->uri()}) → {$status}";
                }
            }
        }

        $this->assertSame([], $failures, 'Dashboard routes not returning 200: '.implode(', ', $failures));
    }

    public function test_no_dashboard_page_leaks_to_a_non_admin(): void
    {
        $user = User::factory()->create();

        foreach ($this->gettableRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'dashboard')) {
                continue;
            }

            $status = $this->actingAs($user)->get('/'.$route->uri())->getStatusCode();

            $this->assertContains($status, [403, 302], "{$route->uri()} leaked to a non-admin ({$status}).");
        }
    }

    /**
     * THE SIDEBAR-ROUTE TRAP, killed permanently.
     *
     * Every route() name referenced anywhere in a view must resolve.
     */
    public function test_no_view_references_a_route_name_that_does_not_exist(): void
    {
        $missing = [];

        foreach ($this->viewFiles() as $file) {
            // (?<!->) excludes $request->route('token'), which reads a
            // route PARAMETER and has nothing to do with route names.
            preg_match_all("/(?<!->)\broute\(\s*'([a-z0-9_.\-]+)'/i", (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $name) {
                if (Route::getRoutes()->getByName($name) === null) {
                    $missing[] = basename($file).' → '.$name;
                }
            }
        }

        $this->assertSame([], $missing, 'Views reference unknown route names: '.implode(', ', $missing));
    }

    /**
     * The same check for lroute(), the locale-aware wrapper — it resolves
     * through route() internally, so an unknown name throws there too.
     */
    public function test_no_view_references_an_unknown_lroute_name(): void
    {
        $missing = [];

        foreach ($this->viewFiles() as $file) {
            preg_match_all("/\blroute\(\s*'([a-z0-9_.\-]+)'/i", (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $name) {
                if (Route::getRoutes()->getByName($name) === null) {
                    $missing[] = basename($file).' → '.$name;
                }
            }
        }

        $this->assertSame([], $missing, 'Views reference unknown lroute names: '.implode(', ', $missing));
    }

    /**
     * The sidebar builds its links from a list of route names. Any that is
     * not registered must be SKIPPED, not rendered — that is what lets a
     * section be added later without leaving a dead link in the meantime,
     * and what stops removing one from 500-ing the whole dashboard.
     */
    public function test_the_sidebar_renders_only_routes_that_exist(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get(route('dashboard'))->getContent();

        preg_match_all('/href="([^"]*\/dashboard[^"]*)"/', $html, $matches);

        $this->assertNotEmpty($matches[1], 'The sidebar rendered no dashboard links at all.');

        foreach (array_unique($matches[1]) as $href) {
            $path = parse_url($href, PHP_URL_PATH) ?? '/';

            $this->assertNotNull(
                Route::getRoutes()->match(
                    Request::create($path, 'GET')
                ),
                "Sidebar links to an unroutable path: {$path}"
            );
        }
    }

    public function test_a_whitelisted_but_unbuilt_route_does_not_appear_as_a_sidebar_link(): void
    {
        // dashboard.packages.index is listed in the sidebar's section
        // definition but is not registered yet — it must simply not
        // render, rather than throwing on route().
        $this->assertFalse(Route::has('dashboard.packages.index'));

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('/dashboard/packages', false);
    }

    /**
     * @return array<int, string>
     */
    private function viewFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
