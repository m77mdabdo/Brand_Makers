<?php

namespace Database\Seeders;

use App\Models\EstimatorItem;
use Illuminate\Database\Seeder;

/**
 * The cost estimator's line-item catalogue.
 *
 * SEEDED PRICED AT ZERO AND INACTIVE, ON PURPOSE.
 *
 * The item labels and groups are real — they are the deliverables this
 * studio actually quotes for, so the catalogue itself is production
 * content. The prices are not: nobody has set them, and an estimator
 * seeded with invented figures would quote visitors numbers the business
 * never agreed to, on a page whose entire purpose is to state a price.
 *
 * `price` is NOT NULL by design (an unpriced line cannot be summed), so
 * "no price yet" is expressed the only other honest way available:
 * is_active = false. A fresh install therefore has a complete catalogue
 * and an empty public estimator, and the estimator starts working the
 * moment an admin prices and activates the items. An empty estimator is a
 * far better failure than a lying one.
 *
 * For a working estimator in development, run DemoDataSeeder — it prices
 * and activates these same rows with clearly-labelled sample figures.
 *
 * Idempotent on `key`, and neither price nor is_active is re-applied to an
 * existing row: re-seeding must never blank prices an admin has entered or
 * silently switch the public estimator back off.
 */
class EstimatorItemSeeder extends Seeder
{
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
    }

    /**
     * @return array<int, array{key: string, group: string, label: array<string, string>}>
     */
    private function items(): array
    {
        return [
            // --- الهوية البصرية ------------------------------------------
            ['key' => 'logo-design', 'group' => 'identity', 'label' => ['ar' => 'تصميم الشعار', 'en' => 'Logo design']],
            ['key' => 'logo-variants', 'group' => 'identity', 'label' => ['ar' => 'تنويعات الشعار', 'en' => 'Logo variants']],
            ['key' => 'colour-palette', 'group' => 'identity', 'label' => ['ar' => 'لوحة الألوان', 'en' => 'Colour palette']],
            ['key' => 'typography-system', 'group' => 'identity', 'label' => ['ar' => 'نظام الخطوط', 'en' => 'Typography system']],
            ['key' => 'brand-guidelines', 'group' => 'identity', 'label' => ['ar' => 'دليل استخدام الهوية', 'en' => 'Brand usage guide']],

            // --- الاستراتيجية ---------------------------------------------
            ['key' => 'discovery-session', 'group' => 'strategy', 'label' => ['ar' => 'جلسة الاكتشاف', 'en' => 'Discovery session']],
            ['key' => 'market-research', 'group' => 'strategy', 'label' => ['ar' => 'بحث السوق والمنافسين', 'en' => 'Market and competitor research']],
            ['key' => 'positioning', 'group' => 'strategy', 'label' => ['ar' => 'تحديد موقع العلامة', 'en' => 'Brand positioning']],
            ['key' => 'tone-of-voice', 'group' => 'strategy', 'label' => ['ar' => 'نبرة الصوت والرسائل', 'en' => 'Tone of voice and messaging']],

            // --- المطبوعات ------------------------------------------------
            ['key' => 'business-cards', 'group' => 'print', 'label' => ['ar' => 'بطاقات الأعمال', 'en' => 'Business cards']],
            ['key' => 'stationery', 'group' => 'print', 'label' => ['ar' => 'الأوراق الرسمية', 'en' => 'Stationery set']],
            ['key' => 'company-profile', 'group' => 'print', 'label' => ['ar' => 'الملف التعريفي', 'en' => 'Company profile']],
            ['key' => 'brochure', 'group' => 'print', 'label' => ['ar' => 'بروشور', 'en' => 'Brochure']],
            ['key' => 'signage', 'group' => 'print', 'label' => ['ar' => 'اللوحات الإرشادية', 'en' => 'Signage']],
            ['key' => 'packaging', 'group' => 'print', 'label' => ['ar' => 'تصميم التغليف', 'en' => 'Packaging design']],

            // --- الرقمي ---------------------------------------------------
            ['key' => 'website-design', 'group' => 'digital', 'label' => ['ar' => 'تصميم واجهات الموقع', 'en' => 'Website interface design']],
            ['key' => 'landing-page', 'group' => 'digital', 'label' => ['ar' => 'صفحة هبوط', 'en' => 'Landing page']],
            ['key' => 'email-templates', 'group' => 'digital', 'label' => ['ar' => 'قوالب البريد الإلكتروني', 'en' => 'Email templates']],
            ['key' => 'presentation-template', 'group' => 'digital', 'label' => ['ar' => 'قالب العروض التقديمية', 'en' => 'Presentation template']],

            // --- المحتوى --------------------------------------------------
            ['key' => 'social-templates', 'group' => 'content', 'label' => ['ar' => 'قوالب السوشيال ميديا', 'en' => 'Social media templates']],
            ['key' => 'social-monthly', 'group' => 'content', 'label' => ['ar' => 'تصميم منشورات شهرية', 'en' => 'Monthly post design']],
            ['key' => 'animated-logo', 'group' => 'content', 'label' => ['ar' => 'شعار متحرك', 'en' => 'Animated logo']],
            ['key' => 'explainer-video', 'group' => 'content', 'label' => ['ar' => 'مقطع تعريفي', 'en' => 'Explainer video']],
            ['key' => 'photography-direction', 'group' => 'content', 'label' => ['ar' => 'إدارة جلسة تصوير', 'en' => 'Photography art direction']],
        ];
    }
}
