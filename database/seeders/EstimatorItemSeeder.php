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
 * SEEDED WITH NO PRICE, ON PURPOSE. The labels are real services this
 * company performs, so the catalogue is production content. The prices
 * are not: nobody has set them, and an estimator seeded with invented
 * figures would quote visitors numbers the business never agreed to, on a
 * page whose entire purpose is to state a price.
 *
 * price = null now means "على الطلب" rather than "hidden". These rows ship
 * ACTIVE: the visitor sees the full menu of what can be added, each
 * unpriced line is labelled on request, and selecting one turns the quote
 * into a floor instead of contributing a number. Previously the only way
 * to say "not priced yet" was is_active = false, which meant a fresh
 * install rendered an estimator with nothing in it — honest, but useless
 * until an admin had priced every line.
 *
 * Each line starts counting toward the total the moment an admin gives it
 * a figure, with no other change needed.
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
                // null, not 0, and ACTIVE rather than hidden. See the
                // class docblock: an unpriced line now says "على الطلب"
                // instead of vanishing.
                'price' => null,
                'is_active' => true,
            ]);
        }

        EstimatorItem::whereIn('key', self::RETIRED_KEYS)->delete();
    }

    /**
     * `group` is the key of an EstimatorGroup, which carries the label and
     * decides whether the selector admits one answer or several. An item
     * naming a group with no row still renders — under its raw slug — so a
     * rename in progress is not an outage.
     *
     * @return array<int, array{key: string, group: string, label: array<string, string>}>
     */
    private function items(): array
    {
        return [
            // --- الخدمات ---------------------------------------------------
            // The eight formation services, as selectable lines. They mirror
            // the service catalogue by name on purpose: a visitor who read
            // /services should recognise these without translating between
            // two vocabularies.
            ['key' => 'llc-formation', 'group' => 'services', 'label' => ['ar' => 'تأسيس شركة ذات مسؤولية محدودة']],
            ['key' => 'foreign-investment-licence', 'group' => 'services', 'label' => ['ar' => 'رخصة الاستثمار الأجنبي']],
            ['key' => 'company-acquisition', 'group' => 'services', 'label' => ['ar' => 'الاستحواذ على شركة قائمة']],
            ['key' => 'commercial-registration', 'group' => 'services', 'label' => ['ar' => 'السجل التجاري والتوثيق']],
            ['key' => 'national-address-bank-account', 'group' => 'services', 'label' => ['ar' => 'العنوان الوطني وفتح الحساب البنكي']],
            ['key' => 'municipal-sector-licences', 'group' => 'services', 'label' => ['ar' => 'الرخص البلدية والقطاعية']],
            ['key' => 'post-formation-services', 'group' => 'services', 'label' => ['ar' => 'خدمات ما بعد التأسيس']],
            ['key' => 'consulting-and-studies', 'group' => 'services', 'label' => ['ar' => 'الاستشارات والدراسات']],

            // --- الاستحواذ على شركة خارجية ---------------------------------
            // One honest option rather than an invented taxonomy of
            // jurisdictions. The group is single-select, so this reads as a
            // yes/no today and gains variants when the admin adds them.
            ['key' => 'foreign-company-acquisition', 'group' => 'foreign-acquisition', 'label' => ['ar' => 'الاستحواذ على شركة خارجية']],

            // --- خدمات أخرى -----------------------------------------------
            ['key' => 'attestations', 'group' => 'other-services', 'label' => ['ar' => 'التصديقات']],
            ['key' => 'investment-record', 'group' => 'other-services', 'label' => ['ar' => 'صحيفة الاستثمار (السجل الاستثماري)']],
            ['key' => 'financial-statements', 'group' => 'other-services', 'label' => ['ar' => 'القوائم المالية']],
            ['key' => 'bank-account', 'group' => 'other-services', 'label' => ['ar' => 'فتح حساب بنكي']],
            ['key' => 'national-address', 'group' => 'other-services', 'label' => ['ar' => 'العنوان الوطني']],
            ['key' => 'municipal-sector-licence', 'group' => 'other-services', 'label' => ['ar' => 'رخصة بلدية أو قطاعية']],
            ['key' => 'accounting-annual', 'group' => 'other-services', 'label' => ['ar' => 'باقة محاسبة سنوية']],
            ['key' => 'vat-and-zakat', 'group' => 'other-services', 'label' => ['ar' => 'ضريبة القيمة المضافة والزكاة']],
            ['key' => 'social-insurance', 'group' => 'other-services', 'label' => ['ar' => 'التأمينات الاجتماعية']],
            ['key' => 'feasibility-study', 'group' => 'other-services', 'label' => ['ar' => 'دراسة جدوى']],
        ];
    }
}
