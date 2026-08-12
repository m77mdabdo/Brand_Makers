<?php

namespace Database\Seeders;

use App\Models\TrackingSetting;
use Illuminate\Database\Seeder;

/**
 * Creates one inert row per supported pixel/analytics key, so the editor
 * shows a complete form rather than a half-populated one.
 *
 * Every row is seeded with a NULL value and is_active = false: a fresh
 * install must put no third-party script on the page until someone
 * deliberately configures and enables one.
 *
 * The key list comes from TrackingSetting::FORMATS, so the seeder and the
 * validation can never drift apart.
 */
class TrackingSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(TrackingSetting::FORMATS) as $key) {
            TrackingSetting::updateOrCreate(
                ['key' => $key],
                ['value' => null, 'is_active' => false]
            );
        }
    }
}
