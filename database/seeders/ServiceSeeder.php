<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * The service catalogue. Real, shippable copy describing work this studio
 * actually does — nothing invented, no unverifiable claims, no statistics.
 *
 * Slugs are supplied explicitly rather than left to HasSlug so re-running
 * matches the existing row instead of creating a "-2" duplicate.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->services() as $sortOrder => $service) {
            Service::updateOrCreate(
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
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function services(): array
    {
        return [
            [
                'slug' => 'brand-strategy',
                'icon' => 'compass',
                'is_flagship' => true,
                'name' => ['ar' => 'استراتيجية العلامة التجارية', 'en' => 'Brand Strategy'],
                'summary' => [
                    'ar' => 'نحدّد موقع علامتك وجمهورها ورسالتها قبل رسم أي شيء — لأن التصميم بلا استراتيجية مجرد زخرفة.',
                    'en' => 'We define your positioning, audience and message before anything is drawn — design without strategy is decoration.',
                ],
                'body' => [
                    'ar' => '<p>نبدأ بفهم السوق الذي تعمل فيه، والمنافسين الذين يشاركونك المساحة، والجمهور الذي تريد الوصول إليه. من هذا الفهم نصوغ موقعاً واضحاً للعلامة، ونبرة صوت تتحدث بها، ورسائل أساسية تتكرر في كل نقطة تواصل.</p><p>مخرجات هذه المرحلة وثيقة استراتيجية مكتوبة تصبح المرجع الذي تُقاس عليه كل قرارات التصميم والتسويق لاحقاً.</p>',
                    'en' => '<p>We start by understanding the market you operate in, the competitors sharing that space, and the audience you want to reach. From that we shape a clear position for the brand, a tone of voice to speak in, and core messages that repeat across every touchpoint.</p><p>The output is a written strategy document that becomes the reference every later design and marketing decision is measured against.</p>',
                ],
                'requirements' => [
                    'ar' => 'نبذة عن النشاط والخدمات، أمثلة على المنافسين، وصف الجمهور المستهدف، وأي أبحاث أو بيانات سابقة إن وُجدت.',
                    'en' => 'An overview of your business and services, examples of competitors, a description of the target audience, and any prior research or data you have.',
                ],
                'process' => [
                    'ar' => 'جلسة اكتشاف، ثم بحث السوق والمنافسين، ثم صياغة الموقع والرسائل، ثم عرض الاستراتيجية ومراجعتها معك، وأخيراً تسليم الوثيقة النهائية.',
                    'en' => 'A discovery session, market and competitor research, drafting the positioning and messaging, presenting the strategy and reviewing it with you, then delivering the final document.',
                ],
            ],
            [
                'slug' => 'visual-identity',
                'icon' => 'palette',
                'is_flagship' => true,
                'name' => ['ar' => 'تصميم الهوية البصرية', 'en' => 'Visual Identity Design'],
                'summary' => [
                    'ar' => 'نظام بصري كامل — الشعار والألوان والخطوط وقواعد الاستخدام — يجعل علامتك مُتعرَّفاً عليها في أي مكان.',
                    'en' => 'A complete visual system — logo, colour, typography and usage rules — that makes your brand recognisable anywhere.',
                ],
                'body' => [
                    'ar' => '<p>الهوية البصرية ليست شعاراً فقط. نبني نظاماً متكاملاً يشمل الشعار وتنويعاته، ولوحة الألوان، وعائلة الخطوط العربية واللاتينية، وأسلوب الصور والرسومات، وقواعد استخدام كل عنصر.</p><p>تُسلَّم الهوية مع دليل استخدام يشرح ما يجوز وما لا يجوز، حتى يبقى تطبيق العلامة متسقاً مهما تعدّدت الأيدي التي تعمل عليها.</p>',
                    'en' => '<p>A visual identity is more than a logo. We build a complete system: the logo and its variants, a colour palette, an Arabic and Latin type family, an image and illustration style, and rules for using each element.</p><p>It ships with a usage guide setting out what is and is not allowed, so the brand stays consistent however many hands work on it.</p>',
                ],
                'requirements' => [
                    'ar' => 'اسم العلامة النهائي، وثيقة الاستراتيجية إن وُجدت، أي مواد بصرية حالية، وتفضيلاتك ومحاذيرك البصرية.',
                    'en' => 'The final brand name, the strategy document if one exists, any current visual material, and your visual preferences and no-gos.',
                ],
                'process' => [
                    'ar' => 'مراجعة الاستراتيجية، ثم استكشاف الاتجاهات البصرية، ثم تطوير الاتجاه المختار، ثم بناء النظام الكامل، وأخيراً إعداد دليل الهوية وتسليم الملفات.',
                    'en' => 'Reviewing the strategy, exploring visual directions, developing the chosen direction, building out the full system, then preparing the brand guide and handing over the files.',
                ],
            ],
            [
                'slug' => 'logo-design',
                'icon' => 'sparkles',
                'is_flagship' => true,
                'name' => ['ar' => 'تصميم الشعارات', 'en' => 'Logo Design'],
                'summary' => [
                    'ar' => 'شعار مصمَّم ليعمل في كل مقاس ووسط — من ختم صغير إلى واجهة مبنى.',
                    'en' => 'A logo built to work at every size and in every medium — from a small stamp to a building façade.',
                ],
                'body' => [
                    'ar' => '<p>نصمم الشعار بعد فهم ما تمثّله العلامة ومن تخاطب. نقدّم اتجاهات متعددة، ونطوّر الاتجاه الذي تختاره حتى يصل إلى شكله النهائي.</p><p>يُسلَّم الشعار بتنويعاته الأفقية والعمودية والمختصرة، وبنسخ ملوّنة وأحادية اللون، وبصيغ مفتوحة المصدر وأخرى جاهزة للاستخدام المباشر.</p>',
                    'en' => '<p>We design the logo once we understand what the brand stands for and who it speaks to. We present several directions and develop the one you choose through to its final form.</p><p>It is delivered with horizontal, vertical and compact variants, in colour and single-colour versions, as editable source files and as ready-to-use exports.</p>',
                ],
                'requirements' => [
                    'ar' => 'اسم العلامة، وصف موجز للنشاط، أمثلة على شعارات تعجبك وأخرى لا تعجبك، وأماكن الاستخدام المتوقعة.',
                    'en' => 'The brand name, a short description of the business, examples of logos you like and dislike, and where you expect to use it.',
                ],
                'process' => [
                    'ar' => 'الإحاطة، ثم الرسم والاستكشاف، ثم عرض الاتجاهات، ثم التطوير والمراجعات، وأخيراً تسليم ملف الشعار الكامل.',
                    'en' => 'The brief, sketching and exploration, presenting the directions, development and revisions, then delivering the complete logo package.',
                ],
            ],
            [
                'slug' => 'print-collateral',
                'icon' => 'document',
                'name' => ['ar' => 'المطبوعات والمواد التسويقية', 'en' => 'Print & Marketing Collateral'],
                'summary' => [
                    'ar' => 'بطاقات الأعمال والملفات التعريفية والعروض التقديمية — بجودة طباعة ومواصفات جاهزة للمطبعة.',
                    'en' => 'Business cards, company profiles and presentations — print-ready, to spec, and consistent with the identity.',
                ],
                'body' => [
                    'ar' => '<p>نصمم المواد المطبوعة التي تحتاجها علامتك يومياً: بطاقات الأعمال، الأوراق الرسمية، الملف التعريفي، البروشورات، واللوحات الإرشادية.</p><p>تُجهَّز كل الملفات بمواصفات المطبعة الصحيحة — نظام ألوان CMYK، هوامش قص، ودقة مناسبة — ونتابع مع المطبعة عند الحاجة.</p>',
                    'en' => '<p>We design the printed material a brand needs day to day: business cards, stationery, the company profile, brochures and signage.</p><p>Every file is prepared to correct print specification — CMYK, bleed and appropriate resolution — and we liaise with the printer where that helps.</p>',
                ],
                'requirements' => [
                    'ar' => 'ملفات الهوية البصرية، النصوص النهائية المطلوب طباعتها، وتحديد المقاسات والكميات ونوع الورق إن كان محدداً.',
                    'en' => 'The visual identity files, the final copy to be printed, and the sizes, quantities and stock if already decided.',
                ],
                'process' => [
                    'ar' => 'تحديد المواد المطلوبة، ثم التصميم، ثم المراجعة، ثم تجهيز ملفات الطباعة، وأخيراً التسليم أو المتابعة مع المطبعة.',
                    'en' => 'Agreeing the deliverables, designing, reviewing, preparing print files, then handing over or following up with the printer.',
                ],
            ],
            [
                'slug' => 'packaging-design',
                'icon' => 'cube',
                'name' => ['ar' => 'تصميم التغليف', 'en' => 'Packaging Design'],
                'summary' => [
                    'ar' => 'تغليف يحمي المنتج ويبيعه على الرف في آن واحد، بمواصفات إنتاج قابلة للتنفيذ.',
                    'en' => 'Packaging that protects the product and sells it on the shelf, specified so it can actually be produced.',
                ],
                'body' => [
                    'ar' => '<p>نصمم التغليف انطلاقاً من المنتج نفسه: مقاسه، وطريقة عرضه، والمعلومات النظامية التي يجب أن يحملها.</p><p>نعمل على القالب الهندسي (die-line) قبل التصميم البصري، حتى يكون ما نصممه قابلاً للتصنيع فعلاً وليس مجرد صورة جميلة.</p>',
                    'en' => '<p>We design packaging from the product outwards: its dimensions, how it will be displayed, and the regulatory information it has to carry.</p><p>We work on the die-line before the visual design, so what we produce can actually be manufactured rather than only rendered.</p>',
                ],
                'requirements' => [
                    'ar' => 'عينة أو مواصفات المنتج، القالب الهندسي إن وُجد، البيانات النظامية المطلوبة، وملفات الهوية البصرية.',
                    'en' => 'A sample or the product specification, the die-line if one exists, the required regulatory copy, and the visual identity files.',
                ],
                'process' => [
                    'ar' => 'دراسة المنتج والمتطلبات، ثم إعداد القالب، ثم التصميم البصري، ثم المراجعة وعينة الطباعة، وأخيراً تسليم ملفات الإنتاج.',
                    'en' => 'Studying the product and requirements, preparing the die-line, the visual design, review and a print proof, then delivering production files.',
                ],
            ],
            [
                'slug' => 'social-media-design',
                'icon' => 'megaphone',
                'name' => ['ar' => 'تصميم محتوى السوشيال ميديا', 'en' => 'Social Media Design'],
                'summary' => [
                    'ar' => 'قوالب ومنشورات متسقة مع هويتك، مصمَّمة لمقاسات كل منصة على حدة.',
                    'en' => 'Templates and posts consistent with your identity, sized correctly for each platform.',
                ],
                'body' => [
                    'ar' => '<p>نبني نظام قوالب للمنشورات والقصص والأغلفة، بحيث يستطيع فريقك إنتاج محتوى يومي دون أن تنحرف الهوية.</p><p>ونصمم المحتوى نفسه عند الحاجة: منشورات الحملات، إعلانات المنصات، وأغلفة الحسابات — كلٌّ بمقاسه الصحيح لا بقصّ صورة واحدة على كل المنصات.</p>',
                    'en' => '<p>We build a template system for posts, stories and covers, so your team can produce daily content without the identity drifting.</p><p>We also design the content itself where needed: campaign posts, platform ads and profile covers — each at its correct size rather than one image cropped everywhere.</p>',
                ],
                'requirements' => [
                    'ar' => 'ملفات الهوية البصرية، قائمة المنصات المستخدمة، وخطة المحتوى أو النصوص المطلوب تصميمها.',
                    'en' => 'The visual identity files, the list of platforms in use, and the content plan or copy to be designed.',
                ],
                'process' => [
                    'ar' => 'تحديد المنصات والمقاسات، ثم تصميم نظام القوالب، ثم المراجعة، ثم تسليم القوالب القابلة للتحرير مع دليل استخدامها.',
                    'en' => 'Agreeing platforms and sizes, designing the template system, review, then delivering editable templates with a short usage guide.',
                ],
            ],
            [
                'slug' => 'web-ui-design',
                'icon' => 'window',
                'name' => ['ar' => 'تصميم المواقع وتجربة المستخدم', 'en' => 'Web & UI/UX Design'],
                'summary' => [
                    'ar' => 'واجهات عربية وإنجليزية مصمَّمة للقراءة والتحويل، لا للعرض فقط.',
                    'en' => 'Arabic and English interfaces designed to be read and to convert, not just to look at.',
                ],
                'body' => [
                    'ar' => '<p>نصمم الموقع بدءاً من بنية المعلومات ومسار الزائر، قبل الوصول إلى الشكل النهائي. ننتبه بشكل خاص لاتجاه الكتابة من اليمين لليسار، ولاختيار خطوط عربية تُقرأ فعلاً على الشاشة.</p><p>يُسلَّم التصميم كنظام مكوّنات جاهز للتنفيذ، مع حالات الشاشات المختلفة من الجوال إلى سطح المكتب.</p>',
                    'en' => '<p>We design from information architecture and visitor flow outwards, before arriving at the final look. We pay particular attention to right-to-left layout and to choosing Arabic typefaces that genuinely read well on screen.</p><p>The design is delivered as a component system ready for development, with states covering mobile through desktop.</p>',
                ],
                'requirements' => [
                    'ar' => 'ملفات الهوية البصرية، قائمة الصفحات المطلوبة، النصوص والصور المتاحة، وأمثلة على مواقع تعجبك.',
                    'en' => 'The visual identity files, the list of pages required, available copy and imagery, and examples of sites you like.',
                ],
                'process' => [
                    'ar' => 'تحديد بنية الموقع، ثم المخططات الأولية، ثم التصميم البصري، ثم بناء نظام المكوّنات، وأخيراً التسليم للتنفيذ.',
                    'en' => 'Agreeing the site structure, wireframes, visual design, building the component system, then handover for development.',
                ],
            ],
            [
                'slug' => 'motion-graphics',
                'icon' => 'film',
                'name' => ['ar' => 'الموشن جرافيك والفيديو', 'en' => 'Motion Graphics & Video'],
                'summary' => [
                    'ar' => 'شعار متحرك ومقاطع تعريفية وإعلانات قصيرة، مبنية على نفس نظام هويتك.',
                    'en' => 'Animated logos, explainers and short ads, built on the same identity system.',
                ],
                'body' => [
                    'ar' => '<p>نحوّل الهوية الثابتة إلى حركة: شعار متحرك للمقدمات والنهايات، ومقاطع تعريفية تشرح الخدمة، وإعلانات قصيرة مصمَّمة لمنصات محددة.</p><p>نبدأ دائماً بالسيناريو واللوحة القصصية قبل التحريك، لأن تعديل فكرة على الورق أرخص بكثير من تعديلها بعد التنفيذ.</p>',
                    'en' => '<p>We take a static identity into motion: an animated logo for intros and outros, explainer pieces, and short ads designed for specific platforms.</p><p>We always start with a script and storyboard before animating — changing an idea on paper costs far less than changing it after it is built.</p>',
                ],
                'requirements' => [
                    'ar' => 'ملفات الهوية والشعار بصيغة مفتوحة، النص أو الرسالة المطلوب إيصالها، ومدة المقطع والمنصة المستهدفة.',
                    'en' => 'Editable identity and logo files, the message to be communicated, and the target duration and platform.',
                ],
                'process' => [
                    'ar' => 'الإحاطة وكتابة السيناريو، ثم اللوحة القصصية، ثم التصميم، ثم التحريك والصوت، وأخيراً التسليم بالصيغ المطلوبة.',
                    'en' => 'Brief and script, storyboard, design, animation and sound, then delivery in the required formats.',
                ],
            ],
        ];
    }
}
