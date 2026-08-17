<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * The markets our investors come from. Real, factual entries — a country name
 * and a short, non-promissory note about the market. Nothing here claims a
 * client, a case study or a result.
 */
class CountrySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->countries() as $sortOrder => $country) {
            Country::updateOrCreate(
                ['slug' => $country['slug']],
                [
                    'name' => $country['name'],
                    'notes' => $country['notes'],
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function countries(): array
    {
        return [
            [
                'slug' => 'saudi-arabia',
                'name' => ['ar' => 'المملكة العربية السعودية', 'en' => 'Saudi Arabia'],
                'notes' => [
                    'ar' => 'سوقنا الأساسي ومحل التأسيس. نؤسّس ونرخّص في الرياض وجدة والدمام وبقية المناطق.',
                ],
            ],
            [
                'slug' => 'united-arab-emirates',
                'name' => ['ar' => 'الإمارات العربية المتحدة', 'en' => 'United Arab Emirates'],
                'notes' => [
                    'ar' => 'نخدم مستثمرين من دبي وأبوظبي والشارقة يوسّعون نشاطهم إلى السوق السعودي.',
                ],
            ],
            [
                'slug' => 'qatar',
                'name' => ['ar' => 'قطر', 'en' => 'Qatar'],
                'notes' => [
                    'ar' => 'مستثمرون وشركات من الدوحة يؤسّسون كياناتهم في المملكة.',
                ],
            ],
            [
                'slug' => 'kuwait',
                'name' => ['ar' => 'الكويت', 'en' => 'Kuwait'],
                'notes' => [
                    'ar' => 'نعمل عن بُعد مع المستثمرين في الكويت بنفس المسار والمتابعة.',
                ],
            ],
            [
                'slug' => 'bahrain',
                'name' => ['ar' => 'البحرين', 'en' => 'Bahrain'],
                'notes' => [
                    'ar' => 'تأسيس وتراخيص للمنشآت الصغيرة والمتوسطة القادمة من البحرين.',
                ],
            ],
            [
                'slug' => 'oman',
                'name' => ['ar' => 'سلطنة عُمان', 'en' => 'Oman'],
                'notes' => [
                    'ar' => 'مستثمرون من مسقط وخارجها يدخلون السوق السعودي.',
                ],
            ],
            [
                'slug' => 'egypt',
                'name' => ['ar' => 'مصر', 'en' => 'Egypt'],
                'notes' => [
                    'ar' => 'نعمل مع رواد أعمال من القاهرة والإسكندرية يؤسّسون كيانات في المملكة.',
                ],
            ],
            [
                'slug' => 'jordan',
                'name' => ['ar' => 'الأردن', 'en' => 'Jordan'],
                'notes' => [
                    'ar' => 'مستثمرون من عمّان يبحثون عن كيان ورخصة استثمار في السعودية.',
                ],
            ],
        ];
    }
}
