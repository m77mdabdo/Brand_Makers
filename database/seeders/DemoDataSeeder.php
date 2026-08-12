<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * FABRICATED demo data — for local development and demos ONLY.
 *
 * Deliberately NOT called by DatabaseSeeder. Everything below is invented:
 *
 *   DemoTestimonialSeeder      client quotes nobody actually said
 *   DemoCampaignSeeder         campaigns with made-up budget/spend figures
 *   DemoLeadSeeder             enquiries nobody sent, and the revenue
 *                              attached to them
 *   DemoEstimatorPricingSeeder sample prices, so the estimator is usable
 *                              in development
 *   DemoBookingSeeder          appointments nobody booked
 *   DemoOrderSeeder            projects nobody ordered
 *
 * Shipping any of it would put fake client quotes on the public site,
 * quote visitors prices the business never agreed, and make the Reports
 * screen compute ROI against money that was never spent.
 *
 * Every row it creates is marked as sample data in a field a human reads,
 * so an operator looking at the dashboard can tell at a glance — see the
 * SAMPLE_MARKER constant, which is also what the separation test asserts
 * the production seed contains none of.
 *
 * Load it explicitly in dev:
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Order matters: campaigns before leads (leads carry external campaign ids
 * that must resolve), packages before orders, services before bookings.
 * Both of the latter come from the production seed, so run that first.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * The literal marker every fabricated row carries in human-readable
     * text. Asserted against by DemoDataSeparationTest — if a production
     * seeder ever starts emitting this string, that test fails, which is
     * the point.
     */
    public const SAMPLE_MARKER = '(SAMPLE DATA)';

    public function run(): void
    {
        $this->command?->warn(
            'Seeding FABRICATED demo data (testimonials, campaigns, leads, conversions, '
            .'estimator prices, bookings, orders). Never run this against production.'
        );

        $this->call([
            DemoTestimonialSeeder::class,
            DemoCampaignSeeder::class,
            DemoLeadSeeder::class,
            DemoEstimatorPricingSeeder::class,
            DemoBookingSeeder::class,
            DemoOrderSeeder::class,
        ]);
    }
}
