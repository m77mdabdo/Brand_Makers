<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Registration is REMOVED, not hidden.
 *
 * This is a staff-only back office. A live register route would populate
 * the users table with strangers and hand an attacker a valid session to
 * probe from — even though is_admin is guarded, so a self-registered user
 * could never reach the dashboard itself.
 */
class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_no_register_route(): void
    {
        $this->assertFalse(Route::has('register'));
    }

    public function test_the_register_urls_are_not_routable(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.test',
            'password' => 'password-that-is-long',
            'password_confirmation' => 'password-that-is-long',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_controller_and_view_are_gone(): void
    {
        // Deleted rather than merely unrouted: an unreferenced controller
        // is one careless route file away from being live again.
        $this->assertFalse(class_exists(RegisteredUserController::class));
        $this->assertFileDoesNotExist(resource_path('views/auth/register.blade.php'));
    }

    public function test_the_login_screen_offers_no_way_to_register(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertDontSee('/register', false);
    }

    /**
     * Password reset stays: a locked-out admin has no other way back in,
     * and the flow only ever mails an address that already exists.
     */
    public function test_password_reset_is_still_available(): void
    {
        $this->assertTrue(Route::has('password.request'));
        $this->get(route('password.request'))->assertOk();
    }

    public function test_login_sends_an_admin_to_the_dashboard(): void
    {
        $this->seed();
        $admin = User::factory()->admin()->create(['password' => 'correct-horse']);

        $response = $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'correct-horse',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
