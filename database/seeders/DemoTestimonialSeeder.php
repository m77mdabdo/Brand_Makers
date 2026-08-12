<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * FABRICATED testimonials — nobody said these. Loaded only via
 * DemoDataSeeder, never by the default DatabaseSeeder, because publishing
 * invented client quotes as genuine would mislead visitors.
 *
 * client_name is a plain column, so it serves as the match key.
 */
class DemoTestimonialSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->testimonials() as $sortOrder => $testimonial) {
            Testimonial::updateOrCreate(
                ['client_name' => $testimonial['client_name']],
                [
                    'client_title' => $testimonial['client_title'].' '.DemoDataSeeder::SAMPLE_MARKER,
                    'client_country' => $testimonial['client_country'],
                    'quote' => $testimonial['quote'],
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function testimonials(): array
    {
        return [
            [
                'client_name' => 'سارة القحطاني',
                'client_title' => 'مؤسِّسة',
                'client_country' => 'السعودية',
                'quote' => [
                    'ar' => 'بدأنا بجلسة استراتيجية غيّرت فهمنا لعلامتنا قبل أن نرى أي تصميم، وهذا ما جعل الشعار في النهاية قراراً واضحاً لا مفاضلة بين أذواق.',
                    'en' => 'We started with a strategy session that changed how we understood our own brand before we saw any design — which is why the final logo felt like a clear decision rather than a matter of taste.',
                ],
            ],
            [
                'client_name' => 'محمد الشهري',
                'client_title' => 'مدير التسويق',
                'client_country' => 'الإمارات',
                'quote' => [
                    'ar' => 'دليل الهوية هو ما فرق فعلاً. فريقنا صار ينتج محتوى يومياً دون أن نضطر للعودة إليهم في كل تفصيل.',
                    'en' => 'The brand guide is what made the real difference. Our team now produces content daily without coming back to them for every detail.',
                ],
            ],
            [
                'client_name' => 'ليلى الحسن',
                'client_title' => 'شريكة مؤسِّسة',
                'client_country' => 'الأردن',
                'quote' => [
                    'ar' => 'أول استوديو نتعامل معه يتعامل مع النسخة العربية من الهوية بنفس جدية النسخة الإنجليزية، لا كترجمة تُضاف في آخر يوم.',
                    'en' => 'The first studio we have worked with that treats the Arabic half of an identity as seriously as the English one, instead of a translation added on the last day.',
                ],
            ],
            [
                'client_name' => 'Daniel Whitfield',
                'client_title' => 'Managing Director',
                'client_country' => 'United Kingdom',
                'quote' => [
                    'ar' => 'التزموا بالجدول الزمني المتفق عليه، وكانت كل مراجعة مصحوبة بشرح لسبب القرار لا بعرض خيارات فقط.',
                    'en' => 'They held to the agreed timeline, and every review came with the reasoning behind a decision rather than just a set of options.',
                ],
            ],
        ];
    }
}
