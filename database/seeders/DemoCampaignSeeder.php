<?php

namespace Database\Seeders;

use App\Models\Campaign;
use Illuminate\Database\Seeder;

/**
 * FABRICATED campaigns with invented budget/spend figures. Loaded only via
 * DemoDataSeeder — reporting computes ROI from `spend`, so shipping these
 * would make the dashboard report a return on money that was never spent.
 *
 * external_campaign_id values match what DemoLeadSeeder puts on its sample
 * leads, so the resolution to linked_campaign_id works on a fresh seed.
 */
class DemoCampaignSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->campaigns() as $campaign) {
            Campaign::updateOrCreate(
                ['external_campaign_id' => $campaign['external_campaign_id']],
                $campaign + [
                    'currency' => 'SAR',
                    'is_active' => true,
                    'notes' => 'بيانات عينة — احذفها قبل الإطلاق. '.DemoDataSeeder::SAMPLE_MARKER,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function campaigns(): array
    {
        return [
            [
                'name' => 'حملة الهوية البصرية — بحث جوجل '.DemoDataSeeder::SAMPLE_MARKER,
                'platform' => 'google',
                'external_campaign_id' => 'sample_google_search',
                'budget' => 30000.00,
                'spend' => 12400.00,
                'starts_on' => now()->subMonths(2)->toDateString(),
                'ends_on' => now()->addMonth()->toDateString(),
            ],
            [
                'name' => 'حملة إنستغرام — إعادة الاستهداف '.DemoDataSeeder::SAMPLE_MARKER,
                'platform' => 'meta',
                'external_campaign_id' => 'sample_meta_retargeting',
                'budget' => 18000.00,
                'spend' => 9600.00,
                'starts_on' => now()->subMonth()->toDateString(),
            ],
        ];
    }
}
