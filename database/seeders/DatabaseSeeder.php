<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * PRODUCTION seed. Everything called from here is real, shippable content
 * or real configuration — nothing invented.
 *
 * What that rules out, and why:
 *
 *   testimonials       client quotes nobody said
 *   leads              enquiries nobody sent
 *   campaigns          budget and spend figures nobody spent
 *   conversion_events  revenue nobody earned, which would then drive the
 *                      Reports ROI numbers
 *   bookings / orders  appointments and projects that do not exist
 *
 * All of those live in DemoDataSeeder, which this deliberately does NOT
 * call. `php artisan migrate --seed` on production gives you real content
 * and nothing else.
 *
 * For a populated dashboard in development:
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Note two production seeders that ship content WITHOUT prices —
 * PackageSeeder (price = null, "on request") and EstimatorItemSeeder
 * (seeded inactive). Their catalogues are real; their figures are a
 * business decision, and inventing them here would put numbers the studio
 * never agreed to on the public pricing page. Each seeder's docblock says
 * so at the point where someone would otherwise "fix" it.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            // --- accounts and configuration --------------------------
            AdminUserSeeder::class,
            LeadSourceSeeder::class,
            SettingsSeeder::class,
            TrackingSettingSeeder::class,
            BookingAvailabilitySeeder::class,

            // --- content ---------------------------------------------
            PageContentSeeder::class,
            ServiceSeeder::class,
            PackageSeeder::class,
            EstimatorItemSeeder::class,
            CountrySeeder::class,
            FaqSeeder::class,
            ArticleSeeder::class,

            // --- derived from the content above ----------------------
            SeoMetaSeeder::class,
            MenuItemSeeder::class,
        ]);

        $this->translateToEnglish();
    }

    /**
     * Fills the English side of everything seeded above.
     *
     * THE CONTENT SEEDERS WRITE ARABIC ONLY. English lives in one place —
     * the dictionary inside content:translate-to-english — so the two
     * languages can be read against each other instead of drifting apart
     * across nine seeder files. Calling it here is what keeps
     * `migrate:fresh --seed` a single step that yields a bilingual site.
     *
     * Silent by default so the seed output stays readable; run the command
     * directly to see the per-model table and, more importantly, the list
     * of Arabic strings with no translation available.
     *
     * A missing entry degrades rather than breaks: the English field stays
     * empty and spatie/laravel-translatable falls back to the Arabic, so
     * the page renders. The gap is reported, not hidden.
     */
    private function translateToEnglish(): void
    {
        Artisan::call('content:translate-to-english');
    }
}
