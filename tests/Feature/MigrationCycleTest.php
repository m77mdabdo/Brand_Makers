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
     * Indexes belong inside CREATE TABLE, and when they cannot be, the
     * down() must drop them BY NAME.
     *
     * The original rule here was a blanket ban on adding an index through
     * Schema::table(), which is the right default: declaring them in
     * create() is what makes every down() a plain drop and removes the
     * whole errno-1061 (duplicate key name) class of failure.
     *
     * It is not achievable in one case, though — a column added to a table
     * that already exists cannot have its index declared in a create()
     * that already ran. Banning it outright would mean either shipping such
     * a column with no database-level guarantee, or rewriting an earlier
     * migration, which is worse.
     *
     * So this now pins the invariant the ban was protecting rather than the
     * ban itself: an ALTER may add an index, but only if the same file
     * removes it explicitly. A down() that leaves the index behind is what
     * makes the NEXT migrate fail with 1061, and that is the thing worth
     * making impossible.
     */
    public function test_an_index_added_by_an_alter_is_dropped_by_its_own_down(): void
    {
        $offenders = [];

        foreach (File::files(database_path('migrations')) as $file) {
            $source = $file->getContents();

            if (! str_contains($source, 'Schema::table(')) {
                continue;
            }

            // Index names added by an ALTER, e.g. $table->unique('reference', 'bookings_reference_unique').
            if (! preg_match_all('/Schema::table\(.*?\$table->(?:index|unique)\([^)]*?[\'"]([a-z0-9_]+_(?:index|unique))[\'"]\s*\)/s', $source, $matches)) {
                // An index added with no explicit name: the framework
                // generates one, which down() then cannot drop reliably.
                if (preg_match('/Schema::table\(.*?\$table->(?:index|unique)\(/s', $source)) {
                    $offenders[] = $file->getFilename().' (index added by ALTER without an explicit name)';
                }

                continue;
            }

            foreach ($matches[1] as $indexName) {
                if (! str_contains($source, "dropUnique('{$indexName}')") && ! str_contains($source, "dropIndex('{$indexName}')")) {
                    $offenders[] = $file->getFilename()." (adds {$indexName} but never drops it)";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'An index added by an ALTER must be dropped by name in the same down(): '.implode(', ', $offenders)
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
            'estimator_groups', 'estimator_items', 'order_events', 'orders', 'packages',
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
