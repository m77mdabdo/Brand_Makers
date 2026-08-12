<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Seeder;

/**
 * Starter copy for the fixed pages this project needs.
 *
 * THIS SEEDER BOOTSTRAPS, IT DOES NOT MIGRATE.
 *
 * Every write here is create-only: a page that already exists is left
 * completely alone, content and sections both. updateOrCreate would make
 * `db:seed` destructive on a live site — an admin who spent an afternoon
 * rewriting the privacy policy in the dashboard would lose it to anyone
 * reseeding to pick up an unrelated change. That is a bad enough failure
 * to be worth giving up the ability to refresh copy this way.
 *
 * Content changes to an EXISTING install ship as data migrations instead:
 * more work per change, and the right trade. A migration runs once, states
 * its own idempotency, and cannot silently destroy prose.
 *
 * The legal pages are conservative professional drafts, written to be
 * factually accurate about what this site actually does. They are a
 * starting point for review, not a substitute for legal advice, and the
 * privacy policy describes exactly the data handling the schema
 * implements — truncated IPs, consent-gated session recording, and a
 * stated retention window.
 */
class PageContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAbout();
        $this->seedProcess();
        $this->seedPrivacyPolicy();
        $this->seedTerms();
    }

    private function seedAbout(): void
    {
        $this->seedPage('about', [
            'title' => ['ar' => 'من نحن', 'en' => 'About Us'],
            'body' => [
                'ar' => '<p>صنّاع العلامات التجارية استوديو متخصص في بناء الهويات البصرية واستراتيجيات العلامات التجارية. نعمل مع الشركات الناشئة والمؤسسات القائمة على تحويل ما تمثّله من فكرة إلى نظام بصري متماسك يعمل في كل مكان تظهر فيه العلامة.</p>'
                    .'<p>نبدأ كل مشروع بالفهم قبل التصميم: من الجمهور، وما الذي يميّز هذه العلامة، وأين ستُستخدم. ننتهي بنظام كامل ودليل استخدام يمكّن فريقك من تطبيقه بثقة بعد انتهاء تعاوننا.</p>'
                    .'<p>نصمم بالعربية والإنجليزية معاً، ونتعامل مع النسخة العربية كتصميم قائم بذاته لا كترجمة لاحقة.</p>',
                'en' => '<p>Creative Mark is a studio specialising in visual identity and brand strategy. We work with startups and established companies to turn what they stand for into a coherent visual system that holds up everywhere the brand appears.</p>'
                    .'<p>Every project starts with understanding rather than designing: who the audience is, what distinguishes this brand, and where it will be used. Every project ends with a complete system and a usage guide, so your team can apply it confidently after our work together is done.</p>'
                    .'<p>We design in Arabic and English together, and we treat the Arabic version as a design in its own right rather than a translation added afterwards.</p>',
            ],
            'meta_title' => ['ar' => 'من نحن — صنّاع العلامات التجارية', 'en' => 'About Us — Creative Mark'],
            'meta_description' => [
                'ar' => 'استوديو متخصص في بناء الهويات البصرية واستراتيجيات العلامات التجارية للشركات الناشئة والمؤسسات.',
                'en' => 'A studio specialising in visual identity and brand strategy for startups and established companies.',
            ],
        ]);
    }

    private function seedProcess(): void
    {
        $page = $this->seedPage('our-process', [
            'title' => ['ar' => 'كيف نعمل', 'en' => 'How We Work'],
            'body' => [
                'ar' => '<p>مسار واحد واضح لكل مشروع، من الاستشارة الأولى حتى تسليم الملفات. تعرف في كل مرحلة أين وصلنا وما المطلوب منك.</p>',
                'en' => '<p>One clear path for every project, from the first consultation to final handover. At every stage you know where we are and what is needed from you.</p>',
            ],
            'meta_title' => ['ar' => 'كيف نعمل — صنّاع العلامات التجارية', 'en' => 'How We Work — Creative Mark'],
            'meta_description' => [
                'ar' => 'مراحل العمل في مشاريع الهوية البصرية لدى صنّاع العلامات التجارية، من الاستشارة حتى التسليم.',
                'en' => 'The stages of a branding project at Creative Mark, from consultation through to handover.',
            ],
        ]);

        if ($page === null) {
            return;
        }

        $this->seedSections($page, [
            ['key' => 'discovery', 'icon' => 'compass', 'title' => ['ar' => '١. الاكتشاف', 'en' => '1. Discovery'], 'description' => ['ar' => 'جلسة نفهم فيها نشاطك وجمهورك ومنافسيك، ونتفق على نطاق العمل والجدول الزمني.', 'en' => 'A session to understand your business, audience and competitors, and to agree the scope and timeline.']],
            ['key' => 'strategy', 'icon' => 'map', 'title' => ['ar' => '٢. الاستراتيجية', 'en' => '2. Strategy'], 'description' => ['ar' => 'نصوغ موقع العلامة ونبرة صوتها ورسائلها الأساسية في وثيقة مكتوبة تصبح مرجع المشروع.', 'en' => 'We shape the positioning, tone of voice and core messages into a written document that becomes the project reference.']],
            ['key' => 'exploration', 'icon' => 'pencil', 'title' => ['ar' => '٣. الاستكشاف', 'en' => '3. Exploration'], 'description' => ['ar' => 'نقدّم اتجاهات بصرية متعددة مبنية على الاستراتيجية، وتختار الاتجاه الذي نطوّره.', 'en' => 'We present several visual directions grounded in the strategy, and you choose the one we develop.']],
            ['key' => 'development', 'icon' => 'palette', 'title' => ['ar' => '٤. التطوير', 'en' => '4. Development'], 'description' => ['ar' => 'نطوّر الاتجاه المختار إلى نظام كامل: الشعار وتنويعاته، الألوان، الخطوط، وأسلوب الصور.', 'en' => 'We develop the chosen direction into a full system: the logo and its variants, colour, typography and image style.']],
            ['key' => 'delivery', 'icon' => 'package', 'title' => ['ar' => '٥. التسليم', 'en' => '5. Handover'], 'description' => ['ar' => 'تستلم الملفات المفتوحة والجاهزة للاستخدام، مع دليل الهوية الذي يشرح قواعد التطبيق.', 'en' => 'You receive editable and ready-to-use files, with the brand guide setting out the application rules.']],
        ]);
    }

    private function seedPrivacyPolicy(): void
    {
        $this->seedPage('privacy-policy', [
            'title' => ['ar' => 'سياسة الخصوصية', 'en' => 'Privacy Policy'],
            'body' => [
                'ar' => '<h2>ما الذي نجمعه</h2>'
                    .'<p>عندما ترسل نموذج تواصل أو استشارة أو حجز، نحفظ ما كتبته فيه: الاسم، ووسيلة التواصل التي زوّدتنا بها، والخدمة أو الباقة التي تسأل عنها، ورسالتك. نحفظ كذلك مصدر وصولك إلى الموقع (مثل حملة إعلانية أو محرك بحث) لنعرف أي قنواتنا التسويقية تعمل.</p>'
                    .'<h2>تسجيل جلسات الزوار</h2>'
                    .'<p>إذا وافقت عبر إشعار ملفات تعريف الارتباط، نسجّل رحلتك داخل الموقع: الصفحات التي زرتها وترتيبها، ووقت بقائك على كل صفحة، والنموذج الذي أرسلته إن وُجد، ونوع جهازك ومتصفحك.</p>'
                    .'<p><strong>لا نسجّل عنوان IP كاملاً إطلاقاً.</strong> يُقتطع العنوان قبل تخزينه — تُحذف الخانة الأخيرة من عنوان IPv4، ويُحتفظ بأول ٤٨ بت فقط من عنوان IPv6 — فلا يمكن الرجوع منه إلى جهاز بعينه.</p>'
                    .'<p>لا يُسجَّل شيء من ذلك قبل موافقتك، ويمكنك سحب الموافقة في أي وقت بحذف ملفات تعريف الارتباط من إعدادات المتصفح.</p>'
                    .'<h2>مدة الاحتفاظ</h2>'
                    .'<p>تُحذف سجلات الجلسات تلقائياً بعد انقضاء مدة الاحتفاظ المحددة (٩٠ يوماً افتراضياً). تُحفظ بيانات طلبات الاستشارة والمشاريع طوال مدة التعامل وبعدها بالمدة التي تقتضيها المتطلبات النظامية والمحاسبية.</p>'
                    .'<h2>المشاركة مع الغير</h2>'
                    .'<p>لا نبيع بياناتك ولا نؤجّرها. نستخدم أدوات تحليلات وإعلانات من أطراف ثالثة، ولا تعمل هذه الأدوات إلا بعد موافقتك عبر إشعار ملفات تعريف الارتباط.</p>'
                    .'<h2>حقوقك</h2>'
                    .'<p>يمكنك طلب نسخة من بياناتك لدينا أو طلب حذفها، عبر وسائل التواصل المذكورة في صفحة اتصل بنا.</p>',
                'en' => '<h2>What we collect</h2>'
                    .'<p>When you submit a contact, consultation or booking form, we store what you entered in it: your name, the contact details you gave us, the service or package you are asking about, and your message. We also record how you arrived at the site (an ad campaign, a search engine) so we know which of our marketing channels work.</p>'
                    .'<h2>Visitor session recording</h2>'
                    .'<p>If you agree via the cookie notice, we record your journey through the site: the pages you viewed and in what order, how long you spent on each, any form you submitted, and your device and browser type.</p>'
                    .'<p><strong>We never store a full IP address.</strong> The address is truncated before it is stored — the final octet of an IPv4 address is discarded, and only the first 48 bits of an IPv6 address are kept — so it cannot be traced back to an individual device.</p>'
                    .'<p>None of this is recorded before you agree, and you can withdraw consent at any time by clearing this site\'s cookies in your browser settings.</p>'
                    .'<h2>Retention</h2>'
                    .'<p>Session records are deleted automatically once the configured retention window has passed (90 days by default). Consultation and project records are kept for the duration of our engagement and afterwards for as long as legal and accounting requirements demand.</p>'
                    .'<h2>Sharing</h2>'
                    .'<p>We do not sell or rent your data. We use third-party analytics and advertising tools, and none of them run before you have agreed via the cookie notice.</p>'
                    .'<h2>Your rights</h2>'
                    .'<p>You can request a copy of the data we hold about you, or ask us to delete it, using the contact details on our contact page.</p>',
            ],
            'meta_title' => ['ar' => 'سياسة الخصوصية — صنّاع العلامات التجارية', 'en' => 'Privacy Policy — Creative Mark'],
            'meta_description' => [
                'ar' => 'كيف نجمع بياناتك ونستخدمها ونحميها، ومدة الاحتفاظ بها، وحقوقك تجاهها.',
                'en' => 'How we collect, use and protect your data, how long we keep it, and what rights you have.',
            ],
        ]);
    }

    private function seedTerms(): void
    {
        $this->seedPage('terms-and-conditions', [
            'title' => ['ar' => 'الشروط والأحكام', 'en' => 'Terms & Conditions'],
            'body' => [
                'ar' => '<h2>نطاق هذه الشروط</h2>'
                    .'<p>تنظّم هذه الشروط استخدامك لهذا الموقع. أما تفاصيل أي مشروع — النطاق والمخرجات والجدول الزمني والتكلفة وشروط الدفع — فتُحدَّد في عرض سعر منفصل يُعتمد كتابةً قبل بدء العمل، وهو المرجع عند أي اختلاف.</p>'
                    .'<h2>المحتوى المعروض</h2>'
                    .'<p>الأسعار والتقديرات المعروضة على الموقع، بما فيها نتائج حاسبة التكلفة، إرشادية وتقريبية ولا تُعد عرضاً ملزماً. العرض الملزم هو ما يصلك كتابةً بعد جلسة الاستشارة.</p>'
                    .'<h2>الملكية الفكرية</h2>'
                    .'<p>محتوى هذا الموقع وتصاميمه مملوكة لنا. أما مخرجات أي مشروع فتنتقل ملكيتها الكاملة إلى العميل بعد سداد كامل المستحقات، ما لم يُتفق على غير ذلك كتابةً.</p>'
                    .'<h2>الحجوزات</h2>'
                    .'<p>حجز جلسة الاستشارة لا يُعد تأكيداً حتى نؤكده لك. نحتفظ بحق إعادة جدولة أي جلسة مع إشعارك في أقرب وقت ممكن.</p>'
                    .'<h2>حدود المسؤولية</h2>'
                    .'<p>نبذل جهداً معقولاً لإبقاء معلومات الموقع دقيقة ومحدّثة، لكننا لا نضمن خلوه من الأخطاء ولا استمرار توفره دون انقطاع.</p>'
                    .'<h2>التعديلات</h2>'
                    .'<p>قد نحدّث هذه الشروط من وقت لآخر، ويسري التحديث من تاريخ نشره على هذه الصفحة.</p>',
                'en' => '<h2>Scope of these terms</h2>'
                    .'<p>These terms govern your use of this website. The details of any project — scope, deliverables, timeline, cost and payment terms — are set out in a separate proposal agreed in writing before work begins, and that proposal governs in the event of any conflict.</p>'
                    .'<h2>Content on this site</h2>'
                    .'<p>Prices and estimates shown on this site, including the output of the cost estimator, are indicative and approximate and do not constitute a binding offer. The binding offer is the written proposal you receive after a consultation.</p>'
                    .'<h2>Intellectual property</h2>'
                    .'<p>The content and design of this site belong to us. Ownership of project deliverables transfers in full to the client once all fees are paid, unless agreed otherwise in writing.</p>'
                    .'<h2>Bookings</h2>'
                    .'<p>A consultation booking is not confirmed until we confirm it to you. We reserve the right to reschedule a session, giving you as much notice as we can.</p>'
                    .'<h2>Limitation of liability</h2>'
                    .'<p>We make reasonable efforts to keep the information on this site accurate and current, but we do not warrant that it is free of errors or that the site will be continuously available.</p>'
                    .'<h2>Changes</h2>'
                    .'<p>We may update these terms from time to time; an update takes effect from the date it is published on this page.</p>',
            ],
            'meta_title' => ['ar' => 'الشروط والأحكام — صنّاع العلامات التجارية', 'en' => 'Terms & Conditions — Creative Mark'],
            'meta_description' => [
                'ar' => 'شروط استخدام الموقع، والملكية الفكرية، والحجوزات، وحدود المسؤولية.',
                'en' => 'Terms of use for this site, intellectual property, bookings and limitation of liability.',
            ],
        ]);
    }

    /**
     * Create-only. Returns the page when it was just created, and NULL
     * when it already existed — so callers skip seeding sections onto a
     * page an admin may already have edited.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function seedPage(string $slug, array $attributes): ?Page
    {
        if (Page::where('slug', $slug)->exists()) {
            return null;
        }

        return Page::create($attributes + ['slug' => $slug, 'is_published' => true]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function seedSections(Page $page, array $sections): void
    {
        foreach ($sections as $sortOrder => $section) {
            PageSection::create([
                'page_id' => $page->id,
                'key' => $section['key'],
                'sort_order' => $sortOrder,
                'is_active' => true,
                'content' => [
                    'title' => $section['title'],
                    'description' => $section['description'],
                    'icon' => $section['icon'],
                ],
            ]);
        }
    }
}
