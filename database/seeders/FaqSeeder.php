<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * Real answers to the questions this studio is actually asked. Deliberately
 * conservative: no fixed prices, no guaranteed turnarounds, and no claims
 * that would become false the moment the business changed.
 *
 * Matched on the Arabic question text, which is stable and is what makes
 * re-seeding idempotent without adding a key column to the table.
 */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->faqs() as $sortOrder => $faq) {
            $existing = Faq::query()
                ->where('question->ar', $faq['question']['ar'])
                ->first();

            if ($existing) {
                $existing->update([
                    'question' => $faq['question'],
                    'answer' => $faq['answer'],
                    'sort_order' => $sortOrder,
                ]);

                continue;
            }

            Faq::create($faq + ['is_active' => true, 'sort_order' => $sortOrder]);
        }
    }

    /**
     * @return array<int, array<string, array<string, string>>>
     */
    private function faqs(): array
    {
        return [
            [
                'question' => ['ar' => 'كم تستغرق عملية تصميم الهوية البصرية؟', 'en' => 'How long does a visual identity project take?'],
                'answer' => [
                    'ar' => 'تختلف المدة باختلاف حجم المشروع وعدد المخرجات المطلوبة. نحدّد جدولاً زمنياً واضحاً في عرض السعر قبل بدء العمل، ونلتزم به ما دامت المراجعات تصل في مواعيدها.',
                    'en' => 'It depends on the scope and the number of deliverables. We set out a clear timeline in the proposal before work starts, and we hold to it as long as feedback arrives on schedule.',
                ],
            ],
            [
                'question' => ['ar' => 'كم عدد المراجعات المتاحة؟', 'en' => 'How many rounds of revisions are included?'],
                'answer' => [
                    'ar' => 'يحدَّد عدد جولات المراجعة في عرض السعر لكل مشروع. المراجعات ضمن النطاق المتفق عليه مشمولة، أما تغيير الاتجاه بالكامل بعد اعتماده فيُعامَل كنطاق جديد.',
                    'en' => 'The number of revision rounds is set out in each proposal. Revisions within the agreed scope are included; changing direction entirely after approval is treated as new scope.',
                ],
            ],
            [
                'question' => ['ar' => 'ما الملفات التي أستلمها في نهاية المشروع؟', 'en' => 'What files do I receive at the end of a project?'],
                'answer' => [
                    'ar' => 'تستلم الملفات المفتوحة القابلة للتحرير، وملفات جاهزة للاستخدام المباشر بصيغ الويب والطباعة، ودليل استخدام الهوية. الملكية الكاملة تنتقل إليك بعد سداد المستحقات.',
                    'en' => 'You receive editable source files, ready-to-use exports for web and print, and the brand usage guide. Full ownership transfers to you once payment is complete.',
                ],
            ],
            [
                'question' => ['ar' => 'هل تعملون بالعربية والإنجليزية معاً؟', 'en' => 'Do you work in both Arabic and English?'],
                'answer' => [
                    'ar' => 'نعم. نصمم الهويات ثنائية اللغة بحيث تعمل النسختان العربية والإنجليزية بنفس القوة، مع اختيار خطوط عربية مصمَّمة للقراءة لا مجرد مطابقة شكلية للخط اللاتيني.',
                    'en' => 'Yes. We design bilingual identities so the Arabic and English versions work equally well, choosing Arabic typefaces designed to be read rather than merely matched to the Latin.',
                ],
            ],
            [
                'question' => ['ar' => 'هل يمكن البدء بالشعار فقط ثم استكمال الهوية لاحقاً؟', 'en' => 'Can we start with just a logo and add the rest later?'],
                'answer' => [
                    'ar' => 'نعم، ونصمم الشعار من البداية بحيث يتّسع لنظام هوية كامل لاحقاً دون إعادة عمل. لكننا ننصح عادةً بالبدء بالاستراتيجية، لأن الشعار المبني على فهم واضح يعمّر أطول.',
                    'en' => 'Yes, and we design the logo from the outset so a full identity system can be built on it later without rework. We usually recommend starting with strategy, though — a logo built on a clear understanding lasts longer.',
                ],
            ],
            [
                'question' => ['ar' => 'كيف تُحدَّد التكلفة؟', 'en' => 'How is pricing determined?'],
                'answer' => [
                    'ar' => 'تُحدَّد التكلفة بناءً على نطاق العمل وعدد المخرجات ومدة المشروع. يمكنك استخدام حاسبة التكلفة للحصول على تقدير مبدئي، ويظل عرض السعر الرسمي هو المرجع بعد جلسة الاستشارة.',
                    'en' => 'Pricing is based on scope, the number of deliverables and the project duration. You can use the cost estimator for an initial figure; the formal proposal after a consultation is what counts.',
                ],
            ],
            [
                'question' => ['ar' => 'هل تعملون مع العملاء خارج السعودية؟', 'en' => 'Do you work with clients outside Saudi Arabia?'],
                'answer' => [
                    'ar' => 'نعم، نعمل مع عملاء في دول الخليج والشرق الأوسط عن بُعد. جميع مراحل العمل والمراجعات تتم عبر الاجتماعات المرئية والمنصات المشتركة.',
                    'en' => 'Yes — we work remotely with clients across the Gulf and the wider Middle East. Every stage and review runs through video calls and shared platforms.',
                ],
            ],
            [
                'question' => ['ar' => 'ماذا لو لم يعجبني أي من الاتجاهات المعروضة؟', 'en' => 'What if none of the presented directions works for me?'],
                'answer' => [
                    'ar' => 'نعود إلى الإحاطة معك لفهم سبب عدم التوافق، ثم نقدّم جولة استكشاف جديدة ضمن النطاق المتفق عليه. جلسة الاكتشاف في البداية موجودة تحديداً لتقليل احتمال حدوث ذلك.',
                    'en' => 'We go back to the brief with you to understand why, then present a fresh exploration round within the agreed scope. The discovery session at the start exists precisely to make that unlikely.',
                ],
            ],
            [
                'question' => ['ar' => 'هل تقدّمون خدمات الطباعة والتنفيذ؟', 'en' => 'Do you handle printing and production?'],
                'answer' => [
                    'ar' => 'نجهّز ملفات الطباعة بالمواصفات الصحيحة ونتابع مع المطبعة عند الحاجة، لكن التعاقد على الطباعة نفسها يتم مباشرة بينك وبين المطبعة.',
                    'en' => 'We prepare print files to the correct specification and liaise with the printer where that helps, but the printing contract itself is directly between you and the printer.',
                ],
            ],
            [
                'question' => ['ar' => 'كيف أبدأ؟', 'en' => 'How do I get started?'],
                'answer' => [
                    'ar' => 'احجز جلسة استشارة عبر الموقع. نناقش فيها مشروعك واحتياجاتك، ثم نرسل لك عرض سعر مفصّلاً بالنطاق والجدول الزمني والتكلفة.',
                    'en' => 'Book a consultation through the site. We discuss your project and needs, then send a detailed proposal covering scope, timeline and cost.',
                ],
            ],
        ];
    }
}
