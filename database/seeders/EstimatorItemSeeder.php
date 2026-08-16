<?php

namespace Database\Seeders;

use App\Models\EstimatorItem;
use Illuminate\Database\Seeder;

/**
 * The cost estimator's add-on catalogue.
 *
 * THE THREE PACKAGES ARE NOT IN THIS LIST, AND MUST NOT BE. The estimator
 * already takes a Package as its optional starting point — see
 * EstimatorService::quote(), which resolves a package slug and adds its
 * price before summing any line item. Now that the packages carry real
 * figures (13,000 / 22,000 / 30,000 SAR) that base tier works on its own.
 * Seeding the same three tiers as items too would let a visitor select a
 * package AND its twin line item and be quoted the tier twice.
 *
 * So what lives here is only what a client adds ON TOP of a tier.
 *
 * SEEDED PRICED AT ZERO AND INACTIVE, ON PURPOSE — unchanged from the
 * catalogue this replaced, and for the same reason. The labels are real
 * services this company performs, so the catalogue is production content.
 * The prices are not: nobody has set them, and an estimator seeded with
 * invented figures would quote visitors numbers the business never agreed
 * to, on a page whose entire purpose is to state a price.
 *
 * `price` is NOT NULL by design (an unpriced line cannot be summed), so
 * "no price yet" is expressed the only other honest way available:
 * is_active = false. A fresh install therefore ships the full add-on list
 * and an estimator that quotes the three real tiers and nothing else, and
 * each add-on starts counting the moment an admin prices and activates it.
 *
 * ARABIC ONLY; English comes from content:translate-to-english.
 *
 * Idempotent on `key`, and neither price nor is_active is re-applied to an
 * existing row: re-seeding must never blank prices an admin has entered or
 * silently switch an add-on back off.
 */
class EstimatorItemSeeder extends Seeder
{
    /**
     * The branding line items this replaced. Named explicitly so an item
     * an admin added and priced is not swept up with them.
     *
     * @var list<string>
     */
    private const RETIRED_KEYS = [
        'logo-design', 'logo-variants', 'colour-palette', 'typography-system', 'brand-guidelines',
        'discovery-session', 'market-research', 'positioning', 'tone-of-voice',
        'business-cards', 'stationery', 'company-profile', 'brochure', 'signage', 'packaging',
        'website-design', 'landing-page', 'email-templates', 'presentation-template',
        'social-templates', 'social-monthly', 'animated-logo', 'explainer-video', 'photography-direction',
    ];

    public function run(): void
    {
        foreach ($this->items() as $sortOrder => $item) {
            $existing = EstimatorItem::where('key', $item['key'])->first();

            $attributes = [
                'label' => $item['label'],
                'group' => $item['group'],
                'sort_order' => $sortOrder,
                'currency' => 'SAR',
            ];

            if ($existing) {
                $existing->update($attributes);

                continue;
            }

            EstimatorItem::create($attributes + [
                'key' => $item['key'],
                'price' => 0,
                'is_active' => false,
            ]);
        }

        EstimatorItem::whereIn('key', self::RETIRED_KEYS)->delete();
    }

    /**
     * Group keys resolve against site.estimator.groups, falling back to
     * the raw key so a group an admin invents still renders.
     *
     * @return array<int, array{key: string, group: string, label: array<string, string>}>
     */
    private function items(): array
    {
        return [
            // --- التراخيص -------------------------------------------------
            ['key' => 'foreign-investment-licence', 'group' => 'licensing', 'label' => ['ar' => 'رخصة الاستثمار الأجنبي']],
            ['key' => 'municipal-sector-licence', 'group' => 'licensing', 'label' => ['ar' => 'رخصة بلدية أو قطاعية']],

            // --- البنك والعنوان -------------------------------------------
            ['key' => 'bank-account', 'group' => 'banking', 'label' => ['ar' => 'فتح حساب بنكي']],
            ['key' => 'national-address', 'group' => 'banking', 'label' => ['ar' => 'العنوان الوطني']],

            // --- المحاسبة والامتثال ---------------------------------------
            ['key' => 'accounting-annual', 'group' => 'compliance', 'label' => ['ar' => 'باقة محاسبة سنوية']],
            ['key' => 'vat-and-zakat', 'group' => 'compliance', 'label' => ['ar' => 'ضريبة القيمة المضافة والزكاة']],
            ['key' => 'social-insurance', 'group' => 'compliance', 'label' => ['ar' => 'التأمينات الاجتماعية']],

            // --- الاستشارات -----------------------------------------------
            ['key' => 'feasibility-study', 'group' => 'advisory', 'label' => ['ar' => 'دراسة جدوى']],
        ];
    }
}
