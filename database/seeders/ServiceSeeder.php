<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * The service catalogue: company formation and investment in Saudi Arabia.
 *
 * ARABIC ONLY. English is filled by content:translate-to-english, which
 * DatabaseSeeder runs after every content seeder — see that command for
 * why the two languages live apart.
 *
 * WHAT THE COPY MAY AND MAY NOT SAY. Every description here states what
 * this company does on the client's behalf. None of it states a fee, a
 * turnaround, an approval likelihood, or a promise about what a
 * government body will decide — those depend on the activity, the
 * investor's nationality and the authority's own timelines, and putting
 * them on a public page would make the site liable for a schedule it does
 * not control. `requirements` is deliberately phrased as what is
 * COMMONLY asked for, because the exact list follows the consultation.
 *
 * Slugs are supplied explicitly rather than left to HasSlug, so
 * re-running matches the existing row instead of creating a "-2"
 * duplicate. They are English because they are URLs.
 */
class ServiceSeeder extends Seeder
{
    /**
     * The branding catalogue this replaced.
     *
     * Listed by slug and deleted explicitly rather than by "delete
     * anything not in my list", which would silently destroy a service an
     * admin added in the dashboard. Naming them is what makes the removal
     * auditable — and what stops it growing teeth later.
     *
     * @var list<string>
     */
    private const RETIRED_SLUGS = [
        'brand-strategy',
        'visual-identity',
        'logo-design',
        'print-collateral',
        'packaging-design',
        'social-media-design',
        'web-ui-design',
        'motion-graphics',
    ];

    public function run(): void
    {
        foreach ($this->services() as $sortOrder => $service) {
            Service::withTrashed()->updateOrCreate(
                ['slug' => $service['slug']],
                [
                    'name' => $service['name'],
                    'summary' => $service['summary'],
                    'body' => $service['body'],
                    'requirements' => $service['requirements'],
                    'process' => $service['process'],
                    'icon' => $service['icon'],
                    'is_active' => true,
                    'is_flagship' => $service['is_flagship'] ?? false,
                    'sort_order' => $sortOrder,
                    'deleted_at' => null,
                ]
            );
        }

        $this->retireBrandingCatalogue();
    }

    /**
     * Force-deletes the branding services, and only those.
     *
     * forceDelete rather than a soft delete: these rows describe work this
     * company does not do, and a soft-deleted row still surfaces in the
     * dashboard's trash for someone to restore by mistake. Nothing points
     * at them — Service has no incoming foreign keys — so there is nothing
     * to orphan.
     */
    private function retireBrandingCatalogue(): void
    {
        Service::withTrashed()->whereIn('slug', self::RETIRED_SLUGS)->forceDelete();
    }

    /**
     * The eight services, in the order a client meets them: form the
     * entity, licence it, or buy one; then register, bank and licence it;
     * then keep it compliant.
     *
     * @return array<int, array<string, mixed>>
     */
    private function services(): array
    {
        return [
            [
                'slug' => 'llc-formation',
                'icon' => 'building',
                'is_flagship' => true,
                'name' => ['ar' => 'تأسيس شركة ذات مسؤولية محدودة'],
                'summary' => ['ar' => 'الكيان الأكثر شيوعاً للمستثمرين في السوق السعودي: إجراءات واضحة، ومسؤولية محدودة برأس المال.'],
                'body' => [
                    'ar' => '<p>نتولّى مسار تأسيس الشركة ذات المسؤولية المحدودة من أوله إلى آخره: حجز الاسم التجاري، وصياغة عقد التأسيس، واستكمال التوثيق، وصولاً إلى إصدار السجل التجاري والتسجيلات النظامية التي تليه.</p>'
                        .'<p>نبدأ بتحديد النشاط والكيان الأنسب لك قبل أي إجراء، لأن النشاط الذي تختاره في البداية هو ما يحدّد التراخيص التي ستحتاجها لاحقاً، وتصحيحه بعد التأسيس أطول وأكلف من ضبطه من البداية.</p>',
                ],
                'requirements' => ['ar' => 'صور هويات أو جوازات الشركاء، والنشاط التجاري المستهدف، وثلاثة أسماء تجارية مقترحة، ورأس المال ونسب الملكية بين الشركاء.'],
                'process' => ['ar' => 'استشارة نحدّد فيها النشاط والكيان، ثم حجز الاسم التجاري، ثم صياغة عقد التأسيس وتوثيقه، ثم إصدار السجل التجاري واستكمال التسجيلات النظامية.'],
            ],
            [
                'slug' => 'foreign-investment-licence',
                'icon' => 'globe',
                'is_flagship' => true,
                'name' => ['ar' => 'رخصة الاستثمار الأجنبي'],
                'summary' => ['ar' => 'الترخيص الذي يمكّن المستثمر غير السعودي من ممارسة نشاطه في المملكة باسمه ومِلكه.'],
                'body' => [
                    'ar' => '<p>نراجع معك أهلية النشاط الذي تستهدفه للاستثمار الأجنبي، ونجهّز ملف الطلب ومستندات الشركة الأم وتصديقاتها، ثم نقدّم الطلب ونتابعه مع الجهة المختصة حتى صدور الرخصة.</p>'
                        .'<p>تختلف المتطلبات باختلاف النشاط وجنسية المستثمر وهيكل الملكية، ولذلك نبدأ دائماً بمراجعة الأهلية قبل تجهيز أي مستند — حتى لا تُصدَّق أوراق لا يقبلها النشاط المطلوب.</p>',
                ],
                'requirements' => ['ar' => 'مستندات الشركة الأم موثّقة ومصدّقة، والقوائم المالية، وجوازات الملاك، ووصف النشاط المستهدف في المملكة.'],
                'process' => ['ar' => 'مراجعة أهلية النشاط، ثم تجهيز المستندات وتصديقها، ثم تقديم الطلب ومتابعته مع الجهة المختصة حتى صدور الرخصة.'],
            ],
            [
                'slug' => 'company-acquisition',
                'icon' => 'exchange',
                'is_flagship' => true,
                'name' => ['ar' => 'الاستحواذ على شركة قائمة'],
                'summary' => ['ar' => 'شراء كيان قائم بسجله ونشاطه بدل التأسيس من الصفر، مع نقل الملكية نظامياً.'],
                'body' => [
                    'ar' => '<p>نبحث لك عن كيان قائم يناسب نشاطك المستهدف، ونراجع وضعه النظامي والتزاماته قبل الشراء، ثم نتولّى إجراءات نقل الملكية وتعديل عقد التأسيس وتحديث السجل التجاري باسمك.</p>'
                        .'<p>الاستحواذ خيار عملي حين يكون للكيان القائم سجل أو ترخيص أو تاريخ تشغيلي تحتاجه، لكنه يحمل التزامات الكيان السابقة معه — ولذلك تسبق المراجعةُ الشراءَ دائماً في مسارنا.</p>',
                ],
                'requirements' => ['ar' => 'تحديد النشاط والحجم المطلوب، وهويات أو جوازات المشترين، والاتفاق على هيكل الملكية بعد نقلها.'],
                'process' => ['ar' => 'تحديد الكيان المناسب، ثم مراجعة وضعه النظامي والتزاماته، ثم توقيع نقل الملكية وتوثيقه، ثم تحديث السجل التجاري والتسجيلات باسم المالك الجديد.'],
            ],
            [
                'slug' => 'commercial-registration',
                'icon' => 'document',
                'name' => ['ar' => 'السجل التجاري والتوثيق'],
                'summary' => ['ar' => 'إصدار السجل التجاري وتوثيق عقد التأسيس، وتحديثهما عند أي تعديل لاحق.'],
                'body' => [
                    'ar' => '<p>السجل التجاري هو ما يجعل الكيان قائماً فعلياً ويمكّنه من التعاقد وفتح الحساب البنكي والتوظيف. نتولّى إصداره وتوثيق عقد التأسيس المرتبط به.</p>'
                        .'<p>ونتولّى كذلك التعديلات اللاحقة: تغيير النشاط، أو إضافة شريك أو خروجه، أو تعديل رأس المال أو العنوان — وكلها تتطلب تحديث السجل والعقد معاً حتى يبقيا متطابقين.</p>',
                ],
                'requirements' => ['ar' => 'عقد التأسيس المعتمد، وبيانات الشركاء، والنشاط المسجّل، وأي قرار شركاء يخصّ التعديل المطلوب.'],
                'process' => ['ar' => 'مراجعة البيانات المطلوبة، ثم تقديم الطلب، ثم استلام السجل أو نسخته المحدّثة وتسليمها لك.'],
            ],
            [
                'slug' => 'national-address-bank-account',
                'icon' => 'bank',
                'name' => ['ar' => 'العنوان الوطني وفتح الحساب البنكي'],
                'summary' => ['ar' => 'تسجيل العنوان الوطني للشركة وتجهيز ملف فتح الحساب البنكي ومتابعته.'],
                'body' => [
                    'ar' => '<p>العنوان الوطني متطلب نظامي للشركة، ويُطلب في معظم المعاملات الحكومية والبنكية. نسجّله لك ونربطه بسجل الشركة.</p>'
                        .'<p>ثم نجهّز ملف فتح الحساب البنكي بالمستندات التي يطلبها البنك ونتابع الطلب معك. قرار فتح الحساب يعود للبنك وحده ويخضع لإجراءات «اعرف عميلك» لديه، ودورنا أن يصل الملف مكتملاً وصحيحاً من أول مرة.</p>',
                ],
                'requirements' => ['ar' => 'السجل التجاري وعقد التأسيس، وبيانات المفوّض بالتوقيع، وعقد مقر الشركة أو ما يثبت عنوانها.'],
                'process' => ['ar' => 'تسجيل العنوان الوطني وربطه بالسجل، ثم تجهيز ملف البنك، ثم تقديمه ومتابعته حتى تفعيل الحساب.'],
            ],
            [
                'slug' => 'municipal-sector-licences',
                'icon' => 'stamp',
                'name' => ['ar' => 'الرخص البلدية والقطاعية'],
                'summary' => ['ar' => 'الرخصة البلدية للمقر، وأي ترخيص إضافي يفرضه النشاط نفسه.'],
                'body' => [
                    'ar' => '<p>معظم الأنشطة تحتاج رخصة بلدية للمقر، وبعضها يحتاج ترخيصاً إضافياً من الجهة المنظِّمة لقطاعه — كالصحة أو السياحة أو النقل أو التعليم.</p>'
                        .'<p>نحدّد لك التراخيص التي يفرضها نشاطك تحديداً، ونجهّز اشتراطات المقر ومستندات كل جهة، ونتابع الإصدار حتى يصبح النشاط قابلاً للتشغيل نظامياً.</p>',
                ],
                'requirements' => ['ar' => 'السجل التجاري، وعقد إيجار المقر أو صك ملكيته، ومخطط المقر، وما تفرضه الجهة المنظِّمة للنشاط من اشتراطات.'],
                'process' => ['ar' => 'حصر التراخيص التي يفرضها النشاط، ثم تجهيز اشتراطات المقر والمستندات، ثم تقديم الطلبات ومتابعتها حتى الإصدار.'],
            ],
            [
                'slug' => 'post-formation-services',
                'icon' => 'calculator',
                'is_flagship' => true,
                'name' => ['ar' => 'خدمات ما بعد التأسيس'],
                'summary' => ['ar' => 'محاسبة، وضريبة القيمة المضافة والزكاة، والتأمينات الاجتماعية، والامتثال — بعد أن يبدأ الكيان عمله.'],
                'body' => [
                    'ar' => '<p>التأسيس بداية وليس نهاية. الكيان القائم عليه التزامات دورية: دفاتر محاسبية منتظمة، وإقرارات ضريبة القيمة المضافة والزكاة في مواعيدها، وتسجيل العاملين في التأمينات الاجتماعية، وتجديدات سنوية للسجل والرخص.</p>'
                        .'<p>نتولّى هذه الالتزامات بالنيابة عنك أو نشرف عليها مع فريقك، حتى لا يتحوّل تأخّر إقرار أو تجديد إلى غرامة أو تعليق للنشاط.</p>',
                ],
                'requirements' => ['ar' => 'السجل التجاري وشهادات التسجيل، ومستندات الإيرادات والمصروفات، وبيانات العاملين إن وُجدوا.'],
                'process' => ['ar' => 'مراجعة وضع الكيان والتزاماته القائمة، ثم تسجيله لدى الجهات المطلوبة، ثم تشغيل دورة شهرية وسنوية للدفاتر والإقرارات والتجديدات.'],
            ],
            [
                'slug' => 'consulting-and-studies',
                'icon' => 'chart',
                'name' => ['ar' => 'الاستشارات والدراسات'],
                'summary' => ['ar' => 'اختيار النشاط والكيان المناسبين، ودراسة جدوى تسبق قرار الدخول إلى السوق.'],
                'body' => [
                    'ar' => '<p>قبل التأسيس أسئلة تستحق إجابة: أي نشاط تحديداً، وأي شكل قانوني، وما التراخيص التي يفرضها ذلك، وما التكلفة الحقيقية للتشغيل في السنة الأولى.</p>'
                        .'<p>نقدّم استشارة تجيب على هذه الأسئلة بالأرقام والأنظمة السارية، ودراسة جدوى عند الحاجة تقيس السوق والتكاليف والإيرادات المتوقعة — لتتخذ قرارك على أساس، لا على تقدير.</p>',
                ],
                'requirements' => ['ar' => 'وصف الفكرة أو النشاط المستهدف، والسوق أو المدينة محل الاهتمام، والميزانية المتاحة مبدئياً.'],
                'process' => ['ar' => 'جلسة نفهم فيها هدفك، ثم مراجعة الأنشطة والتراخيص والتكاليف المرتبطة، ثم تسليم توصية مكتوبة أو دراسة جدوى حسب النطاق المتفق عليه.'],
            ],
        ];
    }
}
