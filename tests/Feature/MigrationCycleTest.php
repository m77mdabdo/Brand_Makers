<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every migration must be genuinely reversible, twice.
 *
 * The failure this guards against is specific and has bitten this codebase's
 * predecessor: a migration whose down() is not the exact inverse of its up()
 * leaves the schema half-rolled-back with the migration still recorded as
 * applied, and the second cycle dies with a duplicate-key error (MySQL errno
 * 1061). One cycle is not enough to catch it — the divergence only appears
 * on the SECOND apply, which is why this runs the loop twice.
 *
 * Deliberately NOT using RefreshDatabase: this test drives the migrator
 * itself, so it must own the schema for its duration and rebuild it
 * afterwards for whatever runs next.
 */
class MigrationCycleTest extends TestCase
{
    protected function tearDown(): void
    {
        // Leave a working schema behind whatever happened above.
        Artisan::call('migrate:fresh');

        parent::tearDown();
    }

    public function test_migrate_and_rollback_are_clean_across_two_full_cycles(): void
    {
        Artisan::call('migrate:fresh');

        $expected = $this->tableList();
        $this->assertNotEmpty($expected);

        for ($cycle = 1; $cycle <= 2; $cycle++) {
            $this->assertSame(0, Artisan::call('migrate:rollback'), "Rollback failed on cycle {$cycle}.");

            $this->assertSame(
                ['migrations'],
                $this->tableList(),
                "Cycle {$cycle}: rollback left tables behind, so some down() is not the inverse of its up()."
            );

            $this->assertSame(0, Artisan::call('migrate'), "Re-apply failed on cycle {$cycle}.");

            $this->assertSame(
                $expected,
                $this->tableList(),
                "Cycle {$cycle}: the re-applied schema differs from a fresh one."
            );
        }
    }

    public function test_every_migration_defines_both_up_and_down(): void
    {
        foreach (File::files(database_path('migrations')) as $file) {
            $source = $file->getContents();

            $this->assertStringContainsString('public function up(): void', $source, "{$file->getFilename()} has no up().");
            $this->assertStringContainsString('public function down(): void', $source, "{$file->getFilename()} has no down().");
        }
    }

    /**
     * Indexes are declared inside CREATE TABLE throughout this schema
     * rather than added by later ALTERs — which is what makes every down()
     * a plain drop and removes the whole errno-1061 class of failure. This
     * pins that convention down so a future migration cannot quietly
     * reintroduce it without someone deciding to.
     */
    public function test_no_migration_alters_a_table_to_add_an_index(): void
    {
        $offenders = [];

        foreach (File::files(database_path('migrations')) as $file) {
            $source = $file->getContents();

            if (! str_contains($source, 'Schema::table(')) {
                continue;
            }

            if (preg_match('/Schema::table\(.*?\$table->(index|unique)\(/s', $source)) {
                $offenders[] = $file->getFilename();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Declare indexes inside Schema::create() so down() stays a plain drop: '.implode(', ', $offenders)
        );
    }

    public function test_a_fresh_schema_contains_every_expected_table(): void
    {
        Artisan::call('migrate:fresh');

        $expected = [
            // framework
            'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs',
            'migrations', 'password_reset_tokens', 'sessions', 'users',
            // content
            'articles', 'comments', 'countries', 'faqs', 'media', 'menu_items',
            'page_sections', 'pages', 'seo_meta', 'services', 'settings',
            'testimonials', 'tracking_settings',
            // crm and analytics
            'campaigns', 'conversion_events', 'lead_sources', 'leads', 'visitor_sessions',
            // the four new features
            'booking_availability', 'booking_blackout_dates', 'bookings',
            'estimator_items', 'order_events', 'orders', 'packages',
        ];

        sort($expected);

        $this->assertSame($expected, $this->tableList());
    }

    /**
     * The tables in THIS database, sorted.
     *
     * The schema filter is load-bearing on MySQL: Schema::getTables()
     * reads information_schema and returns every table on the SERVER, not
     * just the connected database — so on a dev machine hosting several
     * projects it happily reports a few hundred tables belonging to
     * something else entirely. SQLite reports its single schema as 'main',
     * hence the fallback.
     *
     * @return list<string>
     */
    private function tableList(): array
    {
        $database = DB::getDatabaseName();

        $tables = collect(Schema::getTables())
            ->filter(fn (array $table) => ! isset($table['schema'])
                || $table['schema'] === $database
                || $table['schema'] === 'main')
            ->map(fn (array $table) => $table['name'])
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $tables;
    }
}
