<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The hot columns are indexed from the start, not after a slow query
 * shows up in production.
 *
 * Each composite below puts its equality-filtered columns first and its
 * sort column last, so one index satisfies both the WHERE and the ORDER BY
 * without a filesort.
 */
class SchemaIndexesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function expectedIndexes(): array
    {
        return [
            'services filter+sort' => ['services', ['is_active', 'sort_order']],
            'page sections by page' => ['page_sections', ['page_id', 'is_active', 'sort_order']],
            'articles publication window' => ['articles', ['is_published', 'published_at']],
            'leads by channel' => ['leads', ['source_platform']],
            'leads inbox badge' => ['leads', ['type', 'is_handled']],
            'countries filter+sort' => ['countries', ['is_active', 'sort_order']],
            'faqs filter+sort' => ['faqs', ['is_active', 'sort_order']],
            'testimonials filter+sort' => ['testimonials', ['is_active', 'sort_order']],
            'packages filter+sort' => ['packages', ['is_active', 'sort_order']],
            'estimator form read' => ['estimator_items', ['is_active', 'group', 'sort_order']],
            'booking capacity check' => ['bookings', ['scheduled_at', 'status']],
            'booking queue' => ['bookings', ['status', 'scheduled_at']],
            'order queue' => ['orders', ['status', 'placed_at']],
            'order timeline' => ['order_events', ['order_id', 'occurred_at']],
            'conversions by date' => ['conversion_events', ['occurred_at']],
            'menu tree' => ['menu_items', ['parent_id', 'is_visible', 'sort_order']],
            'visitor sessions converted filter' => ['visitor_sessions', ['lead_id', 'started_at']],
            'comment moderation queue' => ['comments', ['status', 'created_at']],
        ];
    }

    /**
     * @param  list<string>  $columns
     */
    #[DataProvider('expectedIndexes')]
    public function test_the_hot_column_set_is_indexed(string $table, array $columns): void
    {
        $indexed = array_map(
            fn (array $index) => $index['columns'],
            Schema::getIndexes($table)
        );

        $this->assertContains(
            $columns,
            $indexed,
            "{$table} has no index on (".implode(', ', $columns).').'
        );
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function expectedUniques(): array
    {
        return [
            'service slug' => ['services', ['slug']],
            'page slug' => ['pages', ['slug']],
            'article slug' => ['articles', ['slug']],
            'country slug' => ['countries', ['slug']],
            'package slug' => ['packages', ['slug']],
            'order reference' => ['orders', ['reference']],
            'order tracking code hash' => ['orders', ['tracking_code_hash']],
            'lead source key' => ['lead_sources', ['key']],
            'estimator item key' => ['estimator_items', ['key']],
            'menu item key' => ['menu_items', ['key']],
            'setting key' => ['settings', ['key']],
            'tracking setting key' => ['tracking_settings', ['key']],
            'campaign external id' => ['campaigns', ['external_campaign_id']],
            'visitor session uid' => ['visitor_sessions', ['session_uid']],
            'seo meta owner' => ['seo_meta', ['seo_metable_type', 'seo_metable_id']],
            'page section key within a page' => ['page_sections', ['page_id', 'key']],
            'one availability row per weekday' => ['booking_availability', ['day_of_week']],
            'one blackout per date' => ['booking_blackout_dates', ['date']],
            'user email' => ['users', ['email']],
        ];
    }

    /**
     * @param  list<string>  $columns
     */
    #[DataProvider('expectedUniques')]
    public function test_the_uniqueness_constraint_exists(string $table, array $columns): void
    {
        $unique = array_values(array_map(
            fn (array $index) => $index['columns'],
            array_filter(Schema::getIndexes($table), fn (array $index) => $index['unique'])
        ));

        $this->assertContains(
            $columns,
            $unique,
            "{$table} has no unique constraint on (".implode(', ', $columns).').'
        );
    }
}
