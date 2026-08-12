<?php

namespace Tests\Feature\Dashboard;

use App\Models\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The dashboard write throttle.
 *
 * A stolen session had no ceiling at all before this. The limiter is
 * applied to the whole dashboard group but returns Limit::none() for safe
 * methods, so browsing is unrationed and only writes are counted — which
 * is what makes it safe to blanket the group rather than decorating every
 * write route individually and inevitably missing one.
 */
class DashboardThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        RateLimiter::clear('dashboard-writes');
    }

    public function test_every_dashboard_route_carries_the_write_throttle(): void
    {
        $unthrottled = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'dashboard')) {
                continue;
            }

            if (! in_array('throttle:dashboard-writes', $route->gatherMiddleware(), true)) {
                $unthrottled[] = $route->uri();
            }
        }

        $this->assertSame([], $unthrottled, 'Unthrottled dashboard routes: '.implode(', ', $unthrottled));
    }

    public function test_browsing_is_not_rationed(): void
    {
        $admin = User::factory()->admin()->create();

        // Well past the write limit. A limiter that counted GETs would
        // lock an admin out of their own dashboard for reading it.
        for ($i = 0; $i < 80; $i++) {
            $this->actingAs($admin)->get(route('dashboard.faqs.index'))->assertOk();
        }
    }

    public function test_writes_are_capped_per_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'question' => ['ar' => 'سؤال'],
            'answer' => ['ar' => 'إجابة'],
        ];

        // The seeder already created FAQs, so the ceiling has to be
        // measured against what was there before this loop started.
        $before = Faq::count();
        $lastStatus = 200;

        for ($i = 0; $i < 62; $i++) {
            $lastStatus = $this->actingAs($admin)->post(route('dashboard.faqs.store'), $payload)->getStatusCode();

            if ($lastStatus === 429) {
                break;
            }
        }

        $this->assertSame(429, $lastStatus, 'The write limiter never engaged.');
        $this->assertLessThanOrEqual(60, Faq::count() - $before, 'More writes got through than the limiter allows.');
    }

    public function test_two_admins_do_not_share_a_bucket(): void
    {
        $first = User::factory()->admin()->create();
        $second = User::factory()->admin()->create();

        $payload = ['question' => ['ar' => 'سؤال'], 'answer' => ['ar' => 'إجابة']];

        for ($i = 0; $i < 61; $i++) {
            $status = $this->actingAs($first)->post(route('dashboard.faqs.store'), $payload)->getStatusCode();

            if ($status === 429) {
                break;
            }
        }

        // Keyed on the user id, so one admin hitting the ceiling must not
        // lock out a colleague.
        $this->actingAs($second)
            ->post(route('dashboard.faqs.store'), $payload)
            ->assertRedirect();
    }
}
