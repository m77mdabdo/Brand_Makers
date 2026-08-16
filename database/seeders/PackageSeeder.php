<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * The three formation packages, with the prices the business set.
 *
 * THESE FIGURES ARE REAL AND THAT IS WHY THEY ARE HERE. This seeder used
 * to ship price = null on purpose, because the branding tiers it carried
 * had no agreed pricing and a seeded number would have put a figure on the
 * public pricing page that nobody had approved. That reasoning has not
 * changed — it has been satisfied. 13,000 / 22,000 / 30,000 SAR came from
 * the business, so seeding them invents nothing.
 *
 * The no-overwrite rule stays, and matters more now than it did: price is
 * set when the row is CREATED and never re-applied on an existing row, so
 * re-seeding to pick up a wording change cannot roll a live price back to
 * the number in this file.
 *
 * ARABIC ONLY. English comes from content:translate-to-english, including
 * every entry in the `features` arrays — see that command's
 * translatePackages(), which walks that JSON shape by hand because spatie
 * knows nothing about it.
 *
 * THE FEATURE LISTS ARE A STARTING POINT. They are drawn from the service
 * catalogue and describe the shape of each tier honestly, but the exact
 * inclusions are a commercial decision that belongs to whoever sells them.
 * The admin refines them in Dashboard → الباقات, which is why they are
 * data rather than markup.
 */
class PackageSeeder extends Seeder
{
    /**
     * The branding tiers this replaced.
     *
     * Named explicitly rather than removed by "anything not in my list",
     * so a package an admin created survives. Soft-deleted rather than
     * force-deleted, unlike the retired services: orders and leads carry a
     * package_id, and a hard delete would leave those rows pointing at
     * nothing. The models already read through withTrashed() for exactly
     * this reason.
     *
     * @var list<string>
     */
    private const RETIRED_SLUGS = ['starter', 'growth', 'complete', 'enterprise'];

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
                $existing->restore();

                continue;
            }

            Package::create($attributes + [
                'slug' => $package['slug'],
                'price' => $package['price'],
            ]);
        }

        Package::whereIn('slug', self::RETIRED_SLUGS)->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function packages(): array
    {
        return [
            [
                'slug' => 'formation-basic',
                'price' => 13000,
                'name' => ['ar' => 'الباقة الأساسية'],
                'description' => ['ar' => 'تأسيس بدون استحواذ — كيان جديد بسجل تجاري وعقد تأسيس، لمن يبدأ من الصفر.'],
                'features' => [
                    ['ar' => 'تأسيس الشركة'],
                    ['ar' => 'السجل التجاري'],
                    ['ar' => 'عقد التأسيس'],
                    ['ar' => 'استشارة تحديد النشاط والكيان'],
                ],
            ],
            [
                'slug' => 'formation-acquisition',
                'price' => 22000,
                'is_popular' => true,
                'name' => ['ar' => 'الباقة المتكاملة'],
                'description' => ['ar' => 'تأسيس بالاستحواذ — كيان قائم يُنقل إليك، مع رخصة الاستثمار ومتابعة حكومية.'],
                'features' => [
                    ['ar' => 'كل ما في الباقة الأساسية'],
                    ['ar' => 'الاستحواذ على شركة قائمة'],
                    ['ar' => 'رخصة الاستثمار الأجنبي'],
                    ['ar' => 'متابعة حكومية حتى اكتمال الإجراءات'],
                ],
            ],
            [
                'slug' => 'formation-complete',
                'price' => 30000,
                'name' => ['ar' => 'الباقة الشاملة'],
                'description' => ['ar' => 'تأسيس بالاستحواذ مع خدمات ما بعد التأسيس لمدة ستة أشهر.'],
                'features' => [
                    ['ar' => 'كل ما في الباقة المتكاملة'],
                    ['ar' => 'محاسبة لمدة ٦ أشهر'],
                    ['ar' => 'ضريبة القيمة المضافة والزكاة لمدة ٦ أشهر'],
                    ['ar' => 'التأمينات الاجتماعية لمدة ٦ أشهر'],
                ],
            ],
        ];
    }
}
