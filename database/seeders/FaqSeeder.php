<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * Real answers to the questions a formation client actually asks.
 *
 * ARABIC ONLY; English comes from content:translate-to-english.
 *
 * DELIBERATELY CONSERVATIVE. No processing time, no capital figure, no
 * statement that a particular activity is open to full foreign ownership,
 * and no promise about what an authority will decide. Those belong to the
 * authorities, they change, and an FAQ is exactly where a visitor goes
 * looking for a number to rely on. The package prices ARE quoted, because
 * the business set them and they are published on the pricing page.
 *
 * Matched on the Arabic question text, which is stable and is what makes
 * re-seeding idempotent without adding a key column to the table. That
 * also means changing a question's wording creates a NEW row rather than
 * editing the old one, so the branding questions are removed by name.
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

        Faq::whereIn('question->ar', self::RETIRED_QUESTIONS)->delete();
    }

    /**
     * The branding questions this replaced, by their exact Arabic text —
     * the same key this seeder matches on. Named one by one so a question
     * an admin wrote in the dashboard is never swept up with them.
     *
     * @var list<string>
     */
    private const RETIRED_QUESTIONS = [
        'كم تستغرق عملية تصميم الهوية البصرية؟',
        'كم عدد المراجعات المتاحة؟',
        'ما الملفات التي أستلمها في نهاية المشروع؟',
        'هل تعملون بالعربية والإنجليزية معاً؟',
        'هل يمكن البدء بالشعار فقط ثم استكمال الهوية لاحقاً؟',
        'كيف تُحدَّد التكلفة؟',
        'هل تعملون مع العملاء خارج السعودية؟',
        'ماذا لو لم يعجبني أي من الاتجاهات المعروضة؟',
        'هل تقدّمون خدمات الطباعة والتنفيذ؟',
        /*
         * NOT 'كيف أبدأ؟'. The new set asks the same question — it is the
         * right closing question for either business — and because this
         * seeder matches on the Arabic question text, the row is simply
         * updated with the new answer. Listing it here as well made the
         * seeder create it and then immediately delete it, and the site
         * shipped nine FAQs instead of ten.
         */
    ];

    /**
     * @return array<int, array<string, array<string, string>>>
     */
    private function faqs(): array
    {
        return [
            [
                'question' => ['ar' => 'كم تستغرق إجراءات تأسيس الشركة؟'],
                'answer' => ['ar' => 'تختلف المدة باختلاف النشاط وجنسية المستثمر ونوع التراخيص المطلوبة، ولأن جزءاً منها يجري لدى جهات حكومية لا نتحكّم في جداولها. نعطيك تقديراً واقعياً للمدة في الاستشارة الأولى بعد معرفة نشاطك، ونخبرك بما يمكنك تجهيزه مسبقاً لاختصارها.'],
            ],
            [
                'question' => ['ar' => 'هل يمكن للمستثمر الأجنبي أن يتملّك شركته بالكامل؟'],
                'answer' => ['ar' => 'نعم في كثير من الأنشطة، وليس في كلها. الأهلية وشروطها تحدّدها الجهات المختصة وتختلف من نشاط إلى آخر، ولذلك نبدأ دائماً بمراجعة أهلية نشاطك تحديداً قبل تجهيز أي مستند.'],
            ],
            [
                'question' => ['ar' => 'ما الفرق بين التأسيس والاستحواذ؟'],
                'answer' => ['ar' => 'التأسيس يعطيك كياناً جديداً بلا التزامات سابقة، لكن كل ترخيص فيه يبدأ من الصفر. والاستحواذ ينقل إليك كياناً قائماً بسجله وتاريخه — وبالتزاماته أيضاً، ولذلك تسبق مراجعةُ وضعه النظامي والمالي أي توقيع. الأنسب لك يُحسم بعد معرفة نشاطك المستهدف.'],
            ],
            [
                'question' => ['ar' => 'كم تكلفة التأسيس؟'],
                'answer' => ['ar' => 'باقاتنا معلنة: الباقة الأساسية ١٣٬٠٠٠ ريال، والمتكاملة ٢٢٬٠٠٠ ريال، والشاملة ٣٠٬٠٠٠ ريال. تضاف إليها الرسوم الحكومية وأي خدمات إضافية يحتاجها نشاطك. استخدم حاسبة التكلفة لتقدير مبدئي، ويصلك العرض الدقيق بعد الاستشارة.'],
            ],
            [
                'question' => ['ar' => 'ما المستندات المطلوبة للبدء؟'],
                'answer' => ['ar' => 'في الحالة المعتادة: هويات أو جوازات الشركاء، والنشاط المستهدف، وثلاثة أسماء تجارية مقترحة، ورأس المال ونسب الملكية. أما المستثمر الأجنبي فتُطلب منه إضافةً إلى ذلك مستندات الشركة الأم مصدّقة وقوائمها المالية. نرسل لك القائمة الدقيقة بعد الاستشارة، لأنها تختلف بحسب النشاط.'],
            ],
            [
                'question' => ['ar' => 'هل تفتحون الحساب البنكي أيضاً؟'],
                'answer' => ['ar' => 'نجهّز ملف فتح الحساب بالمستندات التي يطلبها البنك ونتابع الطلب معك حتى تفعيل الحساب. قرار الفتح نفسه يعود للبنك ويخضع لإجراءات «اعرف عميلك» لديه، ودورنا أن يصل الملف مكتملاً وصحيحاً من أول مرة.'],
            ],
            [
                'question' => ['ar' => 'ماذا يحدث بعد إصدار السجل التجاري؟'],
                'answer' => ['ar' => 'يبدأ الكيان التزاماته الدورية: دفاتر محاسبية منتظمة، وإقرارات ضريبة القيمة المضافة والزكاة في مواعيدها، وتسجيل العاملين في التأمينات الاجتماعية، وتجديدات سنوية للسجل والرخص. نتولّى هذه الالتزامات ضمن خدمات ما بعد التأسيس، وهي مشمولة لستة أشهر في الباقة الشاملة.'],
            ],
            [
                'question' => ['ar' => 'هل تعملون مع مستثمرين من خارج المملكة؟'],
                'answer' => ['ar' => 'نعم، وهو جزء أساسي من عملنا. تجري الاستشارة والمتابعة عن بُعد، ونرشدك إلى ما يلزم تصديقه في بلدك قبل إرساله — وهو غالباً أطول جزء في المسار، فالبدء به مبكراً يوفّر وقتاً كبيراً.'],
            ],
            [
                'question' => ['ar' => 'كيف أختار النشاط المناسب؟'],
                'answer' => ['ar' => 'النشاط يحدّد الشكل القانوني والتراخيص والجهة المنظِّمة، ولذلك نبدأ منه لا من الاسم التجاري. في الاستشارة نراجع ما تنوي عمله فعلياً ونطابقه مع تصنيف النشاط الأدق، لأن نشاطين متشابهين في الاسم قد يختلفان تماماً في الشروط.'],
            ],
            [
                'question' => ['ar' => 'كيف أبدأ؟'],
                'answer' => ['ar' => 'احجز استشارة عبر الموقع. نحدّد فيها النشاط والكيان المناسبين ونراجع أهليتك، ثم نرسل لك عرضاً واضحاً بالخطوات والمستندات والتكلفة.'],
            ],
        ];
    }
}
