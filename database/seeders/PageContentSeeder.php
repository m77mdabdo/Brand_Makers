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
        $this->seedActivities();
        $this->seedWhySaudi();
        $this->seedProcess();
        $this->seedPrivacyPolicy();
        $this->seedTerms();
    }

    private function seedAbout(): void
    {
        $this->seedPage('about', [
            'title' => ['ar' => 'من نحن'],
            'body' => [
                'ar' => '<p>صنّاع العلامات التجارية بيت خبرة سعودي متخصص في تأسيس الشركات وتمكين الاستثمار في المملكة العربية السعودية. نرافق المستثمر من أول استشارة حتى تشغيل كيانه، ونكمل معه بخدمات ما بعد التأسيس.</p>'
                    .'<p>نعمل مع المستثمر المحلي والأجنبي على السواء، ونغطّي المسار كاملاً: اختيار النشاط والكيان، والتراخيص، والسجل التجاري، والعنوان الوطني والحساب البنكي، ثم المحاسبة والضريبة والزكاة والتأمينات بعد الانطلاق.</p>'
                    .'<p>رسالتنا أن نجعل رحلة التأسيس واضحة وسريعة وبلا تعقيد: خطوات معلومة، وأسعار معلنة، وفريق يتابع معك حتى تكتمل الإجراءات.</p>',
            ],
            'meta_title' => ['ar' => 'من نحن — صنّاع العلامات التجارية'],
            'meta_description' => [
                'ar' => 'بيت خبرة سعودي متخصص في تأسيس الشركات وتمكين الاستثمار في المملكة، من الاستشارة الأولى حتى ما بعد التأسيس.',
            ],
        ]);
    }

    /**
     * The four economic sectors, as a page of its own.
     *
     * "Which activity?" is the first question a formation client has, and
     * the answer decides the entity, the licences and the cost — so it
     * gets a page rather than a paragraph. The homepage renders these same
     * four sections, so the answer is never more than one click away.
     */
    private function seedActivities(): void
    {
        $page = $this->seedPage('activities', [
            'title' => ['ar' => 'الأنشطة'],
            'body' => [
                'ar' => '<p>نؤسّس ونرخّص شركات في كل القطاعات الاقتصادية، ونرشدك إلى النشاط والكيان الأنسب لما تريد أن تعمل فيه. اختيار النشاط ليس تفصيلاً إدارياً — هو ما يحدّد التراخيص المطلوبة ورأس المال والجهة المنظِّمة، ولذلك نبدأ منه دائماً.</p>',
            ],
            'meta_title' => ['ar' => 'الأنشطة — صنّاع العلامات التجارية'],
            'meta_description' => [
                'ar' => 'الأنشطة التجارية والصناعية والزراعية والخدمية التي نؤسّس ونرخّص شركات فيها في المملكة.',
            ],
        ]);

        if ($page === null) {
            return;
        }

        $this->seedSections($page, [
            ['key' => 'commercial', 'icon' => 'cart', 'title' => ['ar' => 'تجاري'], 'description' => ['ar' => 'الجملة والتجزئة، والاستيراد والتصدير، والتجارة الإلكترونية، والوكالات والتوزيع.']],
            ['key' => 'industrial', 'icon' => 'factory', 'title' => ['ar' => 'صناعي'], 'description' => ['ar' => 'المصانع والتصنيع والتحويل والتعبئة والتغليف.']],
            ['key' => 'agricultural', 'icon' => 'leaf', 'title' => ['ar' => 'زراعي'], 'description' => ['ar' => 'الإنتاج الزراعي والثروة الحيوانية والسمكية والتقنيات الزراعية.']],
            ['key' => 'services', 'icon' => 'concierge', 'title' => ['ar' => 'خدمي'], 'description' => ['ar' => 'الاستشارات والمقاولات والتقنية والسياحة والضيافة والخدمات اللوجستية.']],
        ]);
    }

    /**
     * The investment case for the market.
     *
     * DELIBERATELY UNQUANTIFIED. Every point here is a statement about the
     * regulatory and economic direction of the market, and not one of them
     * carries a growth figure, a ranking or a percentage. Those change,
     * they date badly, and a stale number on a page that exists to build
     * confidence does the opposite. The ownership point is stated with its
     * condition attached — "في كثير من الأنشطة" — because it is not
     * universal and a page that implies otherwise would mislead exactly
     * the reader it is aimed at.
     */
    private function seedWhySaudi(): void
    {
        $page = $this->seedPage('why-invest-saudi-arabia', [
            'title' => ['ar' => 'لماذا تستثمر في السعودية'],
            'body' => [
                'ar' => '<p>تشهد المملكة تحوّلاً اقتصادياً واسعاً فتح قطاعات جديدة أمام الاستثمار المحلي والأجنبي وبسّط كثيراً من إجراءات التأسيس والترخيص. فيما يلي أبرز ما يجعلها وجهة تستحق الدراسة — والتفاصيل الدقيقة تختلف باختلاف النشاط، فابدأ باستشارة قبل أي قرار.</p>',
            ],
            'meta_title' => ['ar' => 'لماذا تستثمر في السعودية — صنّاع العلامات التجارية'],
            'meta_description' => [
                'ar' => 'أبرز عوامل الجذب الاستثماري في المملكة العربية السعودية للمستثمرين المحليين والأجانب.',
            ],
        ]);

        if ($page === null) {
            return;
        }

        $this->seedSections($page, [
            ['key' => 'growing-economy', 'icon' => 'chart', 'title' => ['ar' => 'اقتصاد متنامٍ'], 'description' => ['ar' => 'سوق كبير ومتنوّع تدعمه استثمارات واسعة في البنية التحتية والقطاعات غير النفطية.']],
            ['key' => 'vision-2030', 'icon' => 'compass', 'title' => ['ar' => 'رؤية 2030'], 'description' => ['ar' => 'استراتيجية وطنية تعمل على تنويع الاقتصاد وفتح قطاعات جديدة أمام القطاع الخاص.']],
            ['key' => 'foreign-ownership', 'icon' => 'globe', 'title' => ['ar' => 'تملّك أجنبي كامل في كثير من الأنشطة'], 'description' => ['ar' => 'تتيح الأنظمة للمستثمر الأجنبي تملّك شركته بالكامل في كثير من الأنشطة دون شريك محلي. الأهلية تختلف بحسب النشاط، ونراجعها معك قبل البدء.']],
            ['key' => 'location', 'icon' => 'globe', 'title' => ['ar' => 'موقع استراتيجي يربط ثلاث قارات'], 'description' => ['ar' => 'تتوسط المملكة آسيا وأفريقيا وأوروبا، وهو ما يمنحها موقعاً لوجستياً للوصول إلى أسواق إقليمية وعالمية.']],
            ['key' => 'incentives', 'icon' => 'stamp', 'title' => ['ar' => 'حوافز ومناطق اقتصادية'], 'description' => ['ar' => 'برامج ومناطق اقتصادية خاصة تقدّم مزايا للمستثمرين في قطاعات ذات أولوية.']],
        ]);
    }

    private function seedProcess(): void
    {
        $page = $this->seedPage('our-process', [
            'title' => ['ar' => 'كيف نعمل'],
            'body' => [
                'ar' => '<p>مسار واحد واضح لكل عميل، من الاستشارة الأولى حتى تشغيل الكيان. تعرف في كل مرحلة أين وصلت الإجراءات وما المطلوب منك.</p>',
            ],
            'meta_title' => ['ar' => 'كيف نعمل — صنّاع العلامات التجارية'],
            'meta_description' => [
                'ar' => 'مراحل التأسيس لدى صنّاع العلامات التجارية، من الاستشارة وتحديد النشاط حتى ما بعد التأسيس.',
            ],
        ]);

        if ($page === null) {
            return;
        }

        $this->seedSections($page, [
            ['key' => 'consultation', 'icon' => 'compass', 'title' => ['ar' => '١. استشارة وتحديد النشاط والكيان'], 'description' => ['ar' => 'نفهم هدفك من الدخول إلى السوق، ونحدّد معك النشاط والشكل القانوني الأنسب والتراخيص التي يفرضها.']],
            ['key' => 'documents', 'icon' => 'document', 'title' => ['ar' => '٢. تجهيز المستندات والتراخيص'], 'description' => ['ar' => 'نحصر ما يلزم من مستندات وتصديقات، ونجهّز ملفات التراخيص ونقدّمها إلى الجهات المختصة.']],
            ['key' => 'registration', 'icon' => 'stamp', 'title' => ['ar' => '٣. إصدار السجل والتوثيق'], 'description' => ['ar' => 'نصدر السجل التجاري ونوثّق عقد التأسيس، ونستكمل العنوان الوطني وملف الحساب البنكي.']],
            ['key' => 'post-formation', 'icon' => 'calculator', 'title' => ['ar' => '٤. ما بعد التأسيس والتشغيل'], 'description' => ['ar' => 'نسجّلك لدى الجهات المطلوبة، وندير المحاسبة والإقرارات والتأمينات والتجديدات حتى يعمل الكيان بانتظام.']],
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
                    .'<h2>إشارات الخصوصية في المتصفح</h2>'
                    .'<p>إذا كان متصفحك يرسل إشارة "عدم التتبع" (DNT) أو إشارة التحكم العام بالخصوصية (GPC)، فلا نسجّل جلستك ولا نُشغّل أي أداة تتبع إعلانية، حتى لو سبق أن وافقت على إشعار ملفات تعريف الارتباط. تُفحص هذه الإشارة قبل الموافقة، ولا تستطيع الموافقة إلغاءها.</p>'
                    .'<p>ولا نخزّن كذلك أي شيء يأتي بعد علامة الاستفهام في الروابط التي تزورها؛ نحفظ المسار وحده. أما معطيات الحملات الإعلانية (utm) فتُحفظ في حقول مستقلة محددة سلفاً، لا كنص خام للرابط.</p>'
                    .'<p>لا تُحمَّل بكسلات «ميتا» و«تيك توك» إطلاقاً قبل موافقتك، فلا يصل أي طلب إلى خوادمها. وتعمل أدوات «جوجل» بوضع الموافقة (Consent Mode) وكل إشاراته على «مرفوض» حتى توافق، فلا تحفظ ملفات تعريف ارتباط ولا ترسل معرّفات.</p>'
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
                    .'<h2>Browser privacy signals</h2>'
                    .'<p>If your browser sends a Do Not Track (DNT) or Global Privacy Control (GPC) signal, we do not record your session and we do not run any advertising tracker — even if you previously accepted the cookie notice. The signal is checked before consent, and consent cannot override it.</p>'
                    .'<p>We also do not store anything after the "?" in the URLs you visit; only the path is kept. Campaign parameters (utm) are stored in named fields of their own, never as raw URL text.</p>'
                    .'<p>The Meta and TikTok pixels are not loaded at all before you agree — no request reaches their servers. Google\'s tags run in Consent Mode with every signal set to "denied" until you agree, so they store no cookies and send no identifiers.</p>'
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
