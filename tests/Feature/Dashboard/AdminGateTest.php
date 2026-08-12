<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The dashboard gate.
 *
 * Three distinct outcomes have to hold, and the difference between the
 * last two is the point of the middleware:
 *
 *   guest              → redirected to login (they may well be an admin)
 *   signed in, no flag → 403 (redirecting would lie, and would loop)
 *   admin              → through
 */
class AdminGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every route behind the gate. Data-driven so a dashboard screen added
     * later is one line away from being covered.
     *
     * @return array<string, array{string, string}>
     */
    public static function dashboardRoutes(): array
    {
        return [
            'dashboard home' => ['get', 'dashboard'],
            'settings editor' => ['get', 'dashboard.settings.edit'],
        ];
    }

    #[DataProvider('dashboardRoutes')]
    public function test_a_guest_is_redirected_to_login(string $method, string $routeName): void
    {
        $response = $this->{$method}(route($routeName));

        $response->assertRedirect(route('login'));
    }

    #[DataProvider('dashboardRoutes')]
    public function test_a_signed_in_non_admin_is_forbidden_not_redirected(string $method, string $routeName): void
    {
        $response = $this->actingAs(User::factory()->create())->{$method}(route($routeName));

        // 403, NOT a redirect: they are authenticated, so sending them to
        // login would be untrue — and login bounces an authenticated user
        // straight back here, which is an infinite loop.
        $response->assertForbidden();
    }

    #[DataProvider('dashboardRoutes')]
    public function test_an_admin_gets_through(string $method, string $routeName): void
    {
        $this->seed();

        $response = $this->actingAs(User::factory()->admin()->create())->{$method}(route($routeName));

        $response->assertOk();
    }

    public function test_the_guest_redirect_remembers_where_they_were_going(): void
    {
        $this->get(route('dashboard.settings.edit'));

        // redirect()->guest() stores the intended URL, so after signing in
        // an admin lands on the screen they asked for rather than the
        // dashboard root.
        $this->assertSame(route('dashboard.settings.edit'), session()->get('url.intended'));
    }

    public function test_a_non_admin_cannot_reach_the_gate_by_writing_either(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->put(route('dashboard.settings.update'), ['settings' => ['site_name' => ['ar' => 'اختراق']]]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('settings', ['key' => 'site_name', 'value' => '{"ar":"اختراق"}']);
    }

    public function test_is_admin_cannot_be_granted_through_the_profile_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Still Not An Admin',
            'email' => $user->email,
            'is_admin' => true,
        ]);

        // is_admin is guarded out of $fillable, so the only writer is
        // explicit assignment in code. Without that, this form would be a
        // one-request privilege escalation.
        $this->assertFalse($user->fresh()->is_admin);
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertForbidden();
    }

    public function test_the_dashboard_is_marked_noindex(): void
    {
        $this->seed();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_every_dashboard_route_carries_the_admin_middleware(): void
    {
        $unguarded = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'dashboard')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            if (! in_array('admin', $middleware, true) || ! in_array('auth', $middleware, true)) {
                $unguarded[] = $route->uri();
            }
        }

        // A new dashboard screen registered outside the guarded group
        // would be publicly reachable, and nothing else would notice.
        $this->assertSame([], $unguarded, 'Unguarded dashboard routes: '.implode(', ', $unguarded));
    }
}
