<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * The real package tiers this studio offers.
 *
 * EVERY PACKAGE IS SEEDED WITH price = NULL, ON PURPOSE.
 *
 * The tier names, descriptions and feature lists are genuine: they
 * describe what is actually included at each level, so they are
 * production content and belong here. The PRICES are not — nobody has
 * agreed them, and a seeded number would go straight onto the public
 * pricing page as a figure the business never set. That is exactly the
 * failure the production/demo split exists to prevent.
 *
 * NULL is not a placeholder here, it is a meaning: `price` is nullable
 * precisely so a package can be "على الطلب / on request" rather than free
 * (which 0.00 would say). A fresh install therefore ships a complete,
 * truthful pricing page, and an admin sets real figures in the dashboard.
 *
 * Slugs are explicit so re-running matches instead of duplicating, and
 * prices are NOT re-applied on an existing row — re-seeding must never
 * blank a price an admin has entered.
 */
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->packages() as $sortOrder => $package) {
            $existing = Package::withTrashed()->where('slug', $package['slug'])->first();

            $attributes = [
                'name' => $package['name'],
                'description' => $package['description'],
                'features' => $package['features'],
                'is_popular' => $package['is_popular'] ?? false,
                'is_active' => true,
                'sort_order' => $sortOrder,
                'currency' => 'SAR',
            ];

            if ($existing) {
                // price deliberately omitted: never overwrite a real figure.
                $existing->update($attributes);

                continue;
            }

            Package::create($attributes + ['slug' => $package['slug'], 'price' => null]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function packages(): array
    {
        return [
            [
                'slug' => 'starter',
                'name' => ['ar' => 'الباقة الأساسية', 'en' => 'Starter'],
                'description' => [
                    'ar' => 'للمشاريع الجديدة التي تحتاج شعاراً وأساسيات بصرية للانطلاق.',
                    'en' => 'For new ventures that need a logo and the visual basics to launch.',
                ],
                'features' => [
                    ['ar' => 'تصميم الشعار بثلاثة اتجاهات', 'en' => 'Logo design across three directions'],
                    ['ar' => 'تنويعات الشعار (أفقي، عمودي، مختصر)', 'en' => 'Logo variants (horizontal, vertical, compact)'],
                    ['ar' => 'لوحة الألوان الأساسية', 'en' => 'Core colour palette'],
                    ['ar' => 'اختيار الخطوط العربية واللاتينية', 'en' => 'Arabic and Latin typeface selection'],
                    ['ar' => 'تصميم بطاقة الأعمال', 'en' => 'Business card design'],
                    ['ar' => 'الملفات المفتوحة والجاهزة للاستخدام', 'en' => 'Editable source and ready-to-use files'],
                ],
            ],
            [
                'slug' => 'growth',
                'is_popular' => true,
                'name' => ['ar' => 'باقة النمو', 'en' => 'Growth'],
                'description' => [
                    'ar' => 'هوية بصرية كاملة مع دليل استخدام، للشركات التي تحتاج نظاماً متماسكاً لا شعاراً فقط.',
                    'en' => 'A complete visual identity with a usage guide, for companies that need a coherent system rather than a logo alone.',
                ],
                'features' => [
                    ['ar' => 'كل ما في الباقة الأساسية', 'en' => 'Everything in Starter'],
                    ['ar' => 'جلسة اكتشاف وتحديد الموقع', 'en' => 'Discovery and positioning session'],
                    ['ar' => 'نظام هوية بصرية كامل', 'en' => 'Full visual identity system'],
                    ['ar' => 'أسلوب الصور والرسومات', 'en' => 'Image and illustration style'],
                    ['ar' => 'دليل استخدام الهوية', 'en' => 'Brand usage guide'],
                    ['ar' => 'الأوراق الرسمية والمطبوعات الأساسية', 'en' => 'Stationery and core print collateral'],
                    ['ar' => 'قوالب السوشيال ميديا', 'en' => 'Social media templates'],
                ],
            ],
            [
                'slug' => 'complete',
                'name' => ['ar' => 'الباقة المتكاملة', 'en' => 'Complete'],
                'description' => [
                    'ar' => 'استراتيجية وهوية وتطبيقات رقمية ومطبوعة، لعلامة تُطلق أو تُعاد بناؤها بالكامل.',
                    'en' => 'Strategy, identity and both digital and print applications, for a brand launching or rebuilding entirely.',
                ],
                'features' => [
                    ['ar' => 'كل ما في باقة النمو', 'en' => 'Everything in Growth'],
                    ['ar' => 'وثيقة استراتيجية العلامة التجارية', 'en' => 'Written brand strategy document'],
                    ['ar' => 'بحث السوق والمنافسين', 'en' => 'Market and competitor research'],
                    ['ar' => 'نبرة الصوت والرسائل الأساسية', 'en' => 'Tone of voice and core messaging'],
                    ['ar' => 'شعار متحرك', 'en' => 'Animated logo'],
                    ['ar' => 'تصميم واجهات الموقع', 'en' => 'Website interface design'],
                    ['ar' => 'تصميم التغليف عند الحاجة', 'en' => 'Packaging design where relevant'],
                ],
            ],
            [
                'slug' => 'enterprise',
                'name' => ['ar' => 'باقة المؤسسات', 'en' => 'Enterprise'],
                'description' => [
                    'ar' => 'نطاق يُفصَّل حسب حجم المؤسسة وعدد وحداتها وأسواقها. تبدأ دائماً بجلسة استشارة.',
                    'en' => 'Scope tailored to the size of the organisation, its business units and its markets. Always begins with a consultation.',
                ],
                'features' => [
                    ['ar' => 'كل ما في الباقة المتكاملة', 'en' => 'Everything in Complete'],
                    ['ar' => 'هوية لعدة علامات أو وحدات أعمال', 'en' => 'Identity across multiple brands or business units'],
                    ['ar' => 'نظام هوية موسّع مع حالات استخدام متعددة', 'en' => 'Extended identity system with multiple use cases'],
                    ['ar' => 'ورش عمل مع فرق العميل', 'en' => 'Workshops with the client\'s teams'],
                    ['ar' => 'دعم التطبيق بعد التسليم', 'en' => 'Post-handover implementation support'],
                ],
            ],
        ];
    }
}
