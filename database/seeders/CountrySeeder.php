<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * The markets this studio works in. Real, factual entries — a country name
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
                    'ar' => 'سوقنا الأساسي. نعمل مع الشركات الناشئة والمؤسسات القائمة في الرياض وجدة والدمام وبقية المناطق.',
                    'en' => 'Our home market. We work with startups and established companies in Riyadh, Jeddah, Dammam and beyond.',
                ],
            ],
            [
                'slug' => 'united-arab-emirates',
                'name' => ['ar' => 'الإمارات العربية المتحدة', 'en' => 'United Arab Emirates'],
                'notes' => [
                    'ar' => 'نخدم العملاء في دبي وأبوظبي والشارقة، بهويات ثنائية اللغة تناسب سوقاً متعدد الجنسيات.',
                    'en' => 'We serve clients in Dubai, Abu Dhabi and Sharjah with bilingual identities suited to a multinational market.',
                ],
            ],
            [
                'slug' => 'qatar',
                'name' => ['ar' => 'قطر', 'en' => 'Qatar'],
                'notes' => [
                    'ar' => 'مشاريع هوية بصرية ومطبوعات للشركات في الدوحة.',
                    'en' => 'Visual identity and collateral projects for companies in Doha.',
                ],
            ],
            [
                'slug' => 'kuwait',
                'name' => ['ar' => 'الكويت', 'en' => 'Kuwait'],
                'notes' => [
                    'ar' => 'نعمل عن بُعد مع العملاء في الكويت بنفس مسار العمل المعتمد.',
                    'en' => 'We work remotely with clients in Kuwait through the same established process.',
                ],
            ],
            [
                'slug' => 'bahrain',
                'name' => ['ar' => 'البحرين', 'en' => 'Bahrain'],
                'notes' => [
                    'ar' => 'خدمات الهوية البصرية والتغليف للشركات الصغيرة والمتوسطة.',
                    'en' => 'Visual identity and packaging services for small and medium businesses.',
                ],
            ],
            [
                'slug' => 'oman',
                'name' => ['ar' => 'سلطنة عُمان', 'en' => 'Oman'],
                'notes' => [
                    'ar' => 'مشاريع هوية ومحتوى بصري للعملاء في مسقط وخارجها.',
                    'en' => 'Identity and visual content projects for clients in Muscat and beyond.',
                ],
            ],
            [
                'slug' => 'egypt',
                'name' => ['ar' => 'مصر', 'en' => 'Egypt'],
                'notes' => [
                    'ar' => 'نعمل مع الشركات الناشئة في القاهرة والإسكندرية، بأسعار ونطاقات تناسب السوق المحلي.',
                    'en' => 'We work with startups in Cairo and Alexandria, at scopes suited to the local market.',
                ],
            ],
            [
                'slug' => 'jordan',
                'name' => ['ar' => 'الأردن', 'en' => 'Jordan'],
                'notes' => [
                    'ar' => 'خدمات تصميم الهوية والمواد التسويقية للعملاء في عمّان.',
                    'en' => 'Identity design and marketing collateral for clients in Amman.',
                ],
            ],
        ];
    }
}
