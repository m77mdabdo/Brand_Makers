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
                    'ar' => 'بدأنا باستشارة حدّدت النشاط والكيان قبل أي إجراء، وهذا وحده وفّر علينا تعديلاً كنا سندفع ثمنه لاحقاً.',
                    'en' => 'We started with a consultation that settled the activity and the entity before any procedure — and that alone saved us an amendment we would have paid for later.',
                ],
            ],
            [
                'client_name' => 'محمد الشهري',
                'client_title' => 'مدير التسويق',
                'client_country' => 'الإمارات',
                'quote' => [
                    'ar' => 'خدمات ما بعد التأسيس هي ما فرق فعلاً. الإقرارات والتجديدات تمشي في مواعيدها دون أن نتابعها بأنفسنا.',
                    'en' => 'The post-formation service is what made the real difference. Returns and renewals happen on time without us chasing them.',
                ],
            ],
            [
                'client_name' => 'ليلى الحسن',
                'client_title' => 'شريكة مؤسِّسة',
                'client_country' => 'الأردن',
                'quote' => [
                    'ar' => 'أول جهة تشرح لنا الفرق بين التأسيس والاستحواذ بالأرقام بدل أن تبيعنا الخيار الأغلى مباشرة.',
                    'en' => 'The first firm that explained the difference between forming and acquiring in actual figures, instead of steering us straight to the more expensive option.',
                ],
            ],
            [
                'client_name' => 'Daniel Whitfield',
                'client_title' => 'Managing Director',
                'client_country' => 'United Kingdom',
                'quote' => [
                    'ar' => 'كنا نعرف في كل أسبوع أين وصلت المعاملة وما المطلوب منا، وهو ما لم نجده في تجربتنا السابقة.',
                    'en' => 'Every week we knew where the application had got to and what was needed from us — which is not what we experienced the last time.',
                ],
            ],
        ];
    }
}
