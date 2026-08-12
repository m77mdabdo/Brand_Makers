<?php

namespace Database\Seeders;

use App\Models\EstimatorItem;
use Illuminate\Database\Seeder;

/**
 * FABRICATED prices for the estimator catalogue, so the feature is
 * actually usable in development.
 *
 * EstimatorItemSeeder ships the real line items priced at zero and
 * INACTIVE, because nobody has set real figures and a public estimator
 * quoting invented numbers is worse than no estimator at all. This seeder
 * fills that gap for local work only — it prices and activates the same
 * rows, and it is never called by DatabaseSeeder.
 *
 * The figures below are plausible round numbers chosen to make the
 * estimator's arithmetic visible. They are not a price list.
 *
 * Idempotent on `key`, and it only touches rows the production seeder
 * created — an item an admin has already priced is left alone, so running
 * this on a database that has been configured for real cannot overwrite
 * anything.
 */
class DemoEstimatorPricingSeeder extends Seeder
{
    /**
     * @return array<string, float>
     */
    private function prices(): array
    {
        return [
            'logo-design' => 6000,
            'logo-variants' => 1500,
            'colour-palette' => 1200,
            'typography-system' => 1800,
            'brand-guidelines' => 5000,

            'discovery-session' => 2500,
            'market-research' => 7000,
            'positioning' => 5500,
            'tone-of-voice' => 3500,

            'business-cards' => 900,
            'stationery' => 1800,
            'company-profile' => 6500,
            'brochure' => 3200,
            'signage' => 2800,
            'packaging' => 9000,

            'website-design' => 18000,
            'landing-page' => 4500,
            'email-templates' => 2200,
            'presentation-template' => 3000,

            'social-templates' => 4000,
            'social-monthly' => 5500,
            'animated-logo' => 3800,
            'explainer-video' => 12000,
            'photography-direction' => 6800,
        ];
    }

    public function run(): void
    {
        foreach ($this->prices() as $key => $price) {
            $item = EstimatorItem::where('key', $key)->first();

            if ($item === null) {
                continue;
            }

            // Only price what is still unpriced. A real figure entered by
            // an admin must survive someone running the demo seeder.
            if ((float) $item->price > 0.0) {
                continue;
            }

            $item->update(['price' => $price, 'is_active' => true]);
        }
    }
}
