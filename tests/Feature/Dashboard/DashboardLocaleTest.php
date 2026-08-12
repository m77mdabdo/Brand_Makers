<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's language comes from the signed-in admin's own `locale`
 * column, NOT from the URL — deliberately independent of the public
 * site's route-name-based locale.
 */
class DashboardLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_renders_in_the_admins_own_locale(): void
    {
        $this->seed();

        $arabic = User::factory()->admin()->create(['locale' => 'ar']);
        $this->actingAs($arabic)->get(route('dashboard'))
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee(__('dashboard.home.heading', [], 'ar'), false);

        $english = User::factory()->admin()->create(['locale' => 'en']);
        $this->actingAs($english)->get(route('dashboard'))
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee(__('dashboard.home.heading', [], 'en'), false);
    }

    public function test_the_toggle_flips_only_the_current_users_preference(): void
    {
        $this->seed();

        $admin = User::factory()->admin()->create(['locale' => 'ar']);
        $other = User::factory()->admin()->create(['locale' => 'ar']);

        $this->actingAs($admin)->patch(route('dashboard.locale.update'))->assertRedirect();

        $this->assertSame('en', $admin->fresh()->locale);
        $this->assertSame('ar', $other->fresh()->locale, "One admin must never change another's preference.");

        $this->actingAs($admin)->patch(route('dashboard.locale.update'));
        $this->assertSame('ar', $admin->fresh()->locale);
    }

    public function test_the_toggle_ignores_any_locale_supplied_in_the_payload(): void
    {
        $this->seed();
        $admin = User::factory()->admin()->create(['locale' => 'ar']);

        // It reads no locale from the request at all — a crafted value
        // could otherwise store a language the app has no translations
        // for, rendering the whole dashboard as raw translation keys.
        $this->actingAs($admin)->patch(route('dashboard.locale.update'), ['locale' => 'de']);

        $this->assertSame('en', $admin->fresh()->locale);
    }

    public function test_an_unsupported_stored_locale_falls_back_rather_than_breaking_the_ui(): void
    {
        $this->seed();

        $admin = User::factory()->admin()->create();
        // Written directly, bypassing the toggle — a hand-edited row, or a
        // locale removed from the app after someone had chosen it.
        $admin->forceFill(['locale' => 'de'])->save();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_the_public_site_locale_is_unaffected_by_the_admin_preference(): void
    {
        $this->seed();
        $admin = User::factory()->admin()->create(['locale' => 'en']);

        // An admin who prefers an English dashboard must still get the
        // Arabic homepage at "/" — the two mechanisms are separate.
        $this->actingAs($admin)->get('/')->assertSee('<html lang="ar" dir="rtl">', false);
    }
}
