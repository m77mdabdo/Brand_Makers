<?php

namespace Tests\Feature\Analytics;

use App\Models\Setting;
use App\Models\VisitorSession;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The retention window, which is the half of the privacy promise that is
 * easiest to forget.
 */
class VisitorSessionRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function sessionStartedDaysAgo(int $days): VisitorSession
    {
        return VisitorSession::factory()->create([
            'started_at' => now()->subDays($days),
            'last_activity_at' => now()->subDays($days),
        ]);
    }

    public function test_it_deletes_sessions_older_than_the_configured_window(): void
    {
        $old = $this->sessionStartedDaysAgo(120);
        $recent = $this->sessionStartedDaysAgo(10);

        $this->artisan('visitor-sessions:prune')->assertSuccessful();

        $this->assertDatabaseMissing('visitor_sessions', ['id' => $old->id]);
        $this->assertDatabaseHas('visitor_sessions', ['id' => $recent->id]);
    }

    public function test_the_window_comes_from_settings(): void
    {
        Setting::updateOrCreate(['key' => 'visitor_sessions_retention_days'], ['value' => 7, 'group' => 'visitor_sessions']);

        $justInside = $this->sessionStartedDaysAgo(5);
        $justOutside = $this->sessionStartedDaysAgo(9);

        $this->artisan('visitor-sessions:prune')->assertSuccessful();

        $this->assertDatabaseHas('visitor_sessions', ['id' => $justInside->id]);
        $this->assertDatabaseMissing('visitor_sessions', ['id' => $justOutside->id]);
    }

    public function test_the_days_option_overrides_the_setting(): void
    {
        $session = $this->sessionStartedDaysAgo(30);

        $this->artisan('visitor-sessions:prune', ['--days' => 7])->assertSuccessful();

        $this->assertDatabaseMissing('visitor_sessions', ['id' => $session->id]);
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        $session = $this->sessionStartedDaysAgo(120);

        $this->artisan('visitor-sessions:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Would delete 1 session(s)')
            ->assertSuccessful();

        $this->assertDatabaseHas('visitor_sessions', ['id' => $session->id]);
    }

    public function test_a_retention_of_zero_is_refused_rather_than_deleting_everything(): void
    {
        $session = $this->sessionStartedDaysAgo(1);

        // Passed explicitly, so this bypasses the gate's clamp and tests
        // the command's own floor — the last line of defence before a
        // DELETE that would take today's sessions with it.
        $this->artisan('visitor-sessions:prune', ['--days' => 0])->assertFailed();

        $this->assertDatabaseHas('visitor_sessions', ['id' => $session->id]);
    }

    public function test_a_negative_retention_is_refused(): void
    {
        $this->artisan('visitor-sessions:prune', ['--days' => -30])->assertFailed();
    }

    public function test_running_it_twice_is_a_no_op_the_second_time(): void
    {
        $this->sessionStartedDaysAgo(120);

        $this->artisan('visitor-sessions:prune')->assertSuccessful();
        $this->artisan('visitor-sessions:prune')
            ->expectsOutputToContain('Deleted 0 visitor session(s)')
            ->assertSuccessful();
    }

    public function test_it_deletes_more_than_one_batch(): void
    {
        // Cheaper than 1001 factory rows, and it proves the loop
        // terminates rather than deleting only the first batch.
        VisitorSession::factory()->count(5)->create(['started_at' => now()->subDays(200)]);

        $this->artisan('visitor-sessions:prune')->assertSuccessful();

        $this->assertSame(0, VisitorSession::count());
    }

    /**
     * A retention policy nobody runs is not a retention policy — the
     * privacy policy tells visitors this happens, so the schedule entry
     * is part of the promise rather than a convenience.
     */
    public function test_the_prune_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'visitor-sessions:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('10 3 * * *', $events->first()->expression);
    }
}
