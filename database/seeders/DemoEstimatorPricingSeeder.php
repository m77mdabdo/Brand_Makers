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
            'foreign-investment-licence' => 12000,
            'municipal-sector-licence' => 3500,

            'bank-account' => 2500,
            'national-address' => 800,

            'accounting-annual' => 9000,
            'vat-and-zakat' => 6000,
            'social-insurance' => 2400,

            'feasibility-study' => 15000,
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
