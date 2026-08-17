<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Country;
use App\Models\EstimatorGroup;
use App\Models\EstimatorItem;
use App\Models\Faq;
use App\Models\Package;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\Testimonial;
use App\Services\Cms\HtmlSanitizerService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Fills the English side of every translatable database field that has
 * Arabic content and no English content.
 *
 * PORTED FROM the Bawabat Taasees project, whose implementation this
 * mirrors. Only the mechanism is shared — the dictionary below is written
 * for Creative Mark's own copy, and no Arabic or English string was taken
 * from that project's content.
 *
 * WHAT THIS IS NOT. There is no machine-translation API here, and none was
 * asked for. The dictionary is a hand-authored lookup keyed by the EXACT
 * Arabic source string. That works because this project's content is
 * authored once through seeders rather than typed freely by visitors, so
 * the set of strings is closed and known.
 *
 * WHY A COMMAND RATHER THAN 'en' KEYS IN THE SEEDERS. It keeps every
 * English rendering of the site's copy in ONE file, where it can be read
 * as a translation — side by side with its source — instead of scattered
 * across nine seeders where the two languages drift apart a line at a
 * time. It also makes a gap loud: a string with no entry is REPORTED as
 * "no translation available" rather than silently left blank or guessed
 * at, so new Arabic content cannot quietly ship with an empty English
 * page behind it.
 *
 * RUN ORDER. DatabaseSeeder calls this last, after every content seeder,
 * so `migrate:fresh --seed` produces a bilingual site in one step. Run it
 * by hand after editing Arabic content in the dashboard.
 *
 * HTML bodies are stored here WITH their wrapping tags, matching the
 * source's structure, and still pass through HtmlSanitizerService before
 * being saved — a translation authored here gets no bypass of the
 * sanitizer that every other HTML write in this project goes through.
 *
 * SCOPE. Database content only. The static UI chrome in lang/en/site.php
 * is a PHP file, not a model, and is not reachable from here.
 */
class TranslateContentToEnglish extends Command
{
    protected $signature = 'content:translate-to-english
                            {--force : Overwrite English content that already exists, instead of skipping it}';

    protected $description = 'Writes English translations into every translatable field that has Arabic content and no English content.';

    /**
     * Arabic source string => English translation.
     *
     * Keyed by exact match. Entries are grouped by the seeder that
     * produces them, so a change to a seeder has one obvious place to be
     * mirrored.
     *
     * BRAND NAME. "صنّاع العلامات التجارية" renders as "Creative Mark"
     * throughout — the studio's own Latin name, already set in
     * site.brand.name_latin, not a literal translation of the Arabic.
     *
     * @var array<string, string>
     */
    private array $translations = [
        // -------------------------------------------------------------
        // ServiceSeeder
        // -------------------------------------------------------------
        'تأسيس شركة ذات مسؤولية محدودة' => 'Limited Liability Company Formation',
        'الكيان الأكثر شيوعاً للمستثمرين في السوق السعودي: إجراءات واضحة، ومسؤولية محدودة برأس المال.' => 'The most common entity for investors in the Saudi market: clear procedures, and liability limited to the capital.',
        '<p>نتولّى مسار تأسيس الشركة ذات المسؤولية المحدودة من أوله إلى آخره: حجز الاسم التجاري، وصياغة عقد التأسيس، واستكمال التوثيق، وصولاً إلى إصدار السجل التجاري والتسجيلات النظامية التي تليه.</p><p>نبدأ بتحديد النشاط والكيان الأنسب لك قبل أي إجراء، لأن النشاط الذي تختاره في البداية هو ما يحدّد التراخيص التي ستحتاجها لاحقاً، وتصحيحه بعد التأسيس أطول وأكلف من ضبطه من البداية.</p>' => '<p>We handle the limited liability company formation route from end to end: reserving the trade name, drafting the articles of association, completing notarisation, and on to issuing the Commercial Registration and the statutory registrations that follow it.</p><p>We begin by settling the most suitable activity and entity for you before any procedure starts, because the activity chosen at the outset is what determines the licences needed later — and correcting it after formation takes longer and costs more than getting it right from the start.</p>',
        'صور هويات أو جوازات الشركاء، والنشاط التجاري المستهدف، وثلاثة أسماء تجارية مقترحة، ورأس المال ونسب الملكية بين الشركاء.' => 'Copies of the partners\' ID cards or passports, the target business activity, three proposed trade names, and the capital and the ownership split between the partners.',
        'استشارة نحدّد فيها النشاط والكيان، ثم حجز الاسم التجاري، ثم صياغة عقد التأسيس وتوثيقه، ثم إصدار السجل التجاري واستكمال التسجيلات النظامية.' => 'A consultation to settle the activity and the entity, then reserving the trade name, then drafting and notarising the articles of association, then issuing the Commercial Registration and completing the statutory registrations.',

        'رخصة الاستثمار الأجنبي' => 'Foreign Investment Licence',
        'الترخيص الذي يمكّن المستثمر غير السعودي من ممارسة نشاطه في المملكة باسمه ومِلكه.' => 'The licence that allows a non-Saudi investor to operate in the Kingdom in their own name and under their own ownership.',
        '<p>نراجع معك أهلية النشاط الذي تستهدفه للاستثمار الأجنبي، ونجهّز ملف الطلب ومستندات الشركة الأم وتصديقاتها، ثم نقدّم الطلب ونتابعه مع الجهة المختصة حتى صدور الرخصة.</p><p>تختلف المتطلبات باختلاف النشاط وجنسية المستثمر وهيكل الملكية، ولذلك نبدأ دائماً بمراجعة الأهلية قبل تجهيز أي مستند — حتى لا تُصدَّق أوراق لا يقبلها النشاط المطلوب.</p>' => '<p>We review with you whether your target activity is eligible for foreign investment, prepare the application file along with the parent company\'s documents and their attestations, then submit and follow the application with the competent authority until the licence is issued.</p><p>Requirements differ by activity, by investor nationality and by ownership structure, which is why we always start with an eligibility review before preparing any document — so that papers are not attested for an activity that will not accept them.</p>',
        'مستندات الشركة الأم موثّقة ومصدّقة، والقوائم المالية، وجوازات الملاك، ووصف النشاط المستهدف في المملكة.' => 'The parent company\'s documents, notarised and attested, the financial statements, the owners\' passports, and a description of the target activity in the Kingdom.',
        'مراجعة أهلية النشاط، ثم تجهيز المستندات وتصديقها، ثم تقديم الطلب ومتابعته مع الجهة المختصة حتى صدور الرخصة.' => 'Reviewing the activity\'s eligibility, then preparing and attesting the documents, then submitting and following the application with the competent authority until the licence is issued.',

        'الاستحواذ على شركة قائمة' => 'Acquisition of an Existing Company',
        'شراء كيان قائم بسجله ونشاطه بدل التأسيس من الصفر، مع نقل الملكية نظامياً.' => 'Buying an existing entity with its registration and its activity rather than forming one from scratch, with ownership transferred through the proper legal channels.',
        '<p>نبحث لك عن كيان قائم يناسب نشاطك المستهدف، ونراجع وضعه النظامي والتزاماته قبل الشراء، ثم نتولّى إجراءات نقل الملكية وتعديل عقد التأسيس وتحديث السجل التجاري باسمك.</p><p>الاستحواذ خيار عملي حين يكون للكيان القائم سجل أو ترخيص أو تاريخ تشغيلي تحتاجه، لكنه يحمل التزامات الكيان السابقة معه — ولذلك تسبق المراجعةُ الشراءَ دائماً في مسارنا.</p>' => '<p>We look for an existing entity suited to your target activity, review its legal standing and its liabilities before purchase, then handle the ownership transfer, the amendment of the articles of association, and updating the Commercial Registration into your name.</p><p>Acquisition is the practical choice when an existing entity holds a registration, a licence or an operating history you need — but it carries that entity\'s prior liabilities with it, which is why review always precedes purchase on our route.</p>',
        'تحديد النشاط والحجم المطلوب، وهويات أو جوازات المشترين، والاتفاق على هيكل الملكية بعد نقلها.' => 'Settling the activity and the size required, the buyers\' ID cards or passports, and agreement on the ownership structure after transfer.',
        'تحديد الكيان المناسب، ثم مراجعة وضعه النظامي والتزاماته، ثم توقيع نقل الملكية وتوثيقه، ثم تحديث السجل التجاري والتسجيلات باسم المالك الجديد.' => 'Identifying a suitable entity, then reviewing its legal standing and liabilities, then signing and notarising the ownership transfer, then updating the Commercial Registration and the registrations into the new owner\'s name.',

        'السجل التجاري والتوثيق' => 'Commercial Registration and Notarisation',
        'إصدار السجل التجاري وتوثيق عقد التأسيس، وتحديثهما عند أي تعديل لاحق.' => 'Issuing the Commercial Registration and notarising the articles of association, and updating both whenever anything changes.',
        '<p>السجل التجاري هو ما يجعل الكيان قائماً فعلياً ويمكّنه من التعاقد وفتح الحساب البنكي والتوظيف. نتولّى إصداره وتوثيق عقد التأسيس المرتبط به.</p><p>ونتولّى كذلك التعديلات اللاحقة: تغيير النشاط، أو إضافة شريك أو خروجه، أو تعديل رأس المال أو العنوان — وكلها تتطلب تحديث السجل والعقد معاً حتى يبقيا متطابقين.</p>' => '<p>The Commercial Registration is what makes the entity real in practice and lets it contract, open a bank account and hire. We handle issuing it and notarising the articles of association attached to it.</p><p>We also handle the changes that follow: changing the activity, adding a partner or removing one, amending the capital or the address — each of which requires updating the registration and the articles together so the two stay consistent.</p>',
        'عقد التأسيس المعتمد، وبيانات الشركاء، والنشاط المسجّل، وأي قرار شركاء يخصّ التعديل المطلوب.' => 'The approved articles of association, the partners\' details, the registered activity, and any partners\' resolution relating to the change requested.',
        'مراجعة البيانات المطلوبة، ثم تقديم الطلب، ثم استلام السجل أو نسخته المحدّثة وتسليمها لك.' => 'Reviewing the details required, then submitting the application, then receiving the registration or its updated copy and handing it to you.',

        'العنوان الوطني وفتح الحساب البنكي' => 'National Address and Bank Account Opening',
        'تسجيل العنوان الوطني للشركة وتجهيز ملف فتح الحساب البنكي ومتابعته.' => 'Registering the company\'s National Address, and preparing and following its bank account application.',
        '<p>العنوان الوطني متطلب نظامي للشركة، ويُطلب في معظم المعاملات الحكومية والبنكية. نسجّله لك ونربطه بسجل الشركة.</p><p>ثم نجهّز ملف فتح الحساب البنكي بالمستندات التي يطلبها البنك ونتابع الطلب معك. قرار فتح الحساب يعود للبنك وحده ويخضع لإجراءات «اعرف عميلك» لديه، ودورنا أن يصل الملف مكتملاً وصحيحاً من أول مرة.</p>' => '<p>A National Address is a statutory requirement for the company and is asked for in most government and banking transactions. We register it for you and link it to the company\'s registration.</p><p>We then prepare the bank account file with the documents the bank requires and follow the application with you. The decision to open the account rests with the bank alone and is subject to its own Know Your Customer procedures; our part is that the file arrives complete and correct the first time.</p>',
        'السجل التجاري وعقد التأسيس، وبيانات المفوّض بالتوقيع، وعقد مقر الشركة أو ما يثبت عنوانها.' => 'The Commercial Registration and the articles of association, the authorised signatory\'s details, and the company premises lease or proof of its address.',
        'تسجيل العنوان الوطني وربطه بالسجل، ثم تجهيز ملف البنك، ثم تقديمه ومتابعته حتى تفعيل الحساب.' => 'Registering the National Address and linking it to the registration, then preparing the bank file, then submitting and following it until the account is active.',

        'الرخص البلدية والقطاعية' => 'Municipal and Sector Licences',
        'الرخصة البلدية للمقر، وأي ترخيص إضافي يفرضه النشاط نفسه.' => 'The municipal licence for the premises, and any further licence the activity itself imposes.',
        '<p>معظم الأنشطة تحتاج رخصة بلدية للمقر، وبعضها يحتاج ترخيصاً إضافياً من الجهة المنظِّمة لقطاعه — كالصحة أو السياحة أو النقل أو التعليم.</p><p>نحدّد لك التراخيص التي يفرضها نشاطك تحديداً، ونجهّز اشتراطات المقر ومستندات كل جهة، ونتابع الإصدار حتى يصبح النشاط قابلاً للتشغيل نظامياً.</p>' => '<p>Most activities need a municipal licence for the premises, and some need a further licence from the body regulating their sector — health, tourism, transport and education among them.</p><p>We establish which licences your particular activity requires, prepare the premises conditions and each authority\'s documents, and follow issuance through until the activity can lawfully operate.</p>',
        'السجل التجاري، وعقد إيجار المقر أو صك ملكيته، ومخطط المقر، وما تفرضه الجهة المنظِّمة للنشاط من اشتراطات.' => 'The Commercial Registration, the premises lease or title deed, the premises layout, and whatever conditions the body regulating the activity imposes.',
        'حصر التراخيص التي يفرضها النشاط، ثم تجهيز اشتراطات المقر والمستندات، ثم تقديم الطلبات ومتابعتها حتى الإصدار.' => 'Establishing which licences the activity requires, then preparing the premises conditions and the documents, then submitting and following the applications through to issuance.',

        'خدمات ما بعد التأسيس' => 'Post-Formation Services',
        'محاسبة، وضريبة القيمة المضافة والزكاة، والتأمينات الاجتماعية، والامتثال — بعد أن يبدأ الكيان عمله.' => 'Accounting, VAT and zakat, social insurance and compliance — once the entity is up and running.',
        '<p>التأسيس بداية وليس نهاية. الكيان القائم عليه التزامات دورية: دفاتر محاسبية منتظمة، وإقرارات ضريبة القيمة المضافة والزكاة في مواعيدها، وتسجيل العاملين في التأمينات الاجتماعية، وتجديدات سنوية للسجل والرخص.</p><p>نتولّى هذه الالتزامات بالنيابة عنك أو نشرف عليها مع فريقك، حتى لا يتحوّل تأخّر إقرار أو تجديد إلى غرامة أو تعليق للنشاط.</p>' => '<p>Formation is a beginning, not an end. A live entity carries recurring obligations: orderly accounting books, VAT and zakat returns filed on time, employees registered with social insurance, and annual renewals of the registration and the licences.</p><p>We take these obligations on for you, or supervise them alongside your own team, so that a late return or a late renewal does not turn into a penalty or a suspension of the activity.</p>',
        'السجل التجاري وشهادات التسجيل، ومستندات الإيرادات والمصروفات، وبيانات العاملين إن وُجدوا.' => 'The Commercial Registration and the registration certificates, revenue and expenditure records, and employee details where there are any.',
        'مراجعة وضع الكيان والتزاماته القائمة، ثم تسجيله لدى الجهات المطلوبة، ثم تشغيل دورة شهرية وسنوية للدفاتر والإقرارات والتجديدات.' => 'Reviewing the entity\'s standing and its existing obligations, then registering it with the required authorities, then running a monthly and annual cycle of books, returns and renewals.',

        'الاستشارات والدراسات' => 'Consulting and Feasibility Studies',
        'اختيار النشاط والكيان المناسبين، ودراسة جدوى تسبق قرار الدخول إلى السوق.' => 'Choosing the right activity and entity, and a feasibility study ahead of the decision to enter the market.',
        '<p>قبل التأسيس أسئلة تستحق إجابة: أي نشاط تحديداً، وأي شكل قانوني، وما التراخيص التي يفرضها ذلك، وما التكلفة الحقيقية للتشغيل في السنة الأولى.</p><p>نقدّم استشارة تجيب على هذه الأسئلة بالأرقام والأنظمة السارية، ودراسة جدوى عند الحاجة تقيس السوق والتكاليف والإيرادات المتوقعة — لتتخذ قرارك على أساس، لا على تقدير.</p>' => '<p>Formation is preceded by questions worth answering: which activity exactly, which legal form, which licences that imposes, and what operating in the first year genuinely costs.</p><p>We provide a consultation that answers those questions against the figures and the regulations in force, and a feasibility study where one is needed, measuring the market, the costs and the expected revenue — so that your decision rests on a basis rather than an estimate.</p>',
        'وصف الفكرة أو النشاط المستهدف، والسوق أو المدينة محل الاهتمام، والميزانية المتاحة مبدئياً.' => 'A description of the idea or the target activity, the market or city of interest, and the budget available at this stage.',
        'جلسة نفهم فيها هدفك، ثم مراجعة الأنشطة والتراخيص والتكاليف المرتبطة، ثم تسليم توصية مكتوبة أو دراسة جدوى حسب النطاق المتفق عليه.' => 'A session to understand your objective, then a review of the activities, licences and associated costs, then delivery of a written recommendation or a feasibility study depending on the scope agreed.',

        // -------------------------------------------------------------
        // PackageSeeder — names, descriptions and every `features` entry
        // -------------------------------------------------------------
        'الباقة الأساسية' => 'Basic Package',
        'تأسيس بدون استحواذ — كيان جديد بسجل تجاري وعقد تأسيس، لمن يبدأ من الصفر.' => 'Formation without acquisition — a new entity with a Commercial Registration and articles of association, for starting from scratch.',
        'تأسيس الشركة' => 'Company formation',
        'السجل التجاري' => 'Commercial Registration',
        'عقد التأسيس' => 'Articles of association',
        'استشارة تحديد النشاط والكيان' => 'A consultation to settle the activity and the entity',

        'الباقة المتكاملة' => 'Integrated Package',
        'تأسيس بالاستحواذ — كيان قائم يُنقل إليك، مع رخصة الاستثمار ومتابعة حكومية.' => 'Formation by acquisition — an existing entity transferred to you, with the investment licence and government follow-up.',
        'كل ما في الباقة الأساسية' => 'Everything in the Basic Package',
        'متابعة حكومية حتى اكتمال الإجراءات' => 'Government follow-up until the procedures are complete',

        'الباقة الشاملة' => 'Comprehensive Package',
        'تأسيس بالاستحواذ مع خدمات ما بعد التأسيس لمدة ستة أشهر.' => 'Formation by acquisition, with six months of post-formation services.',
        'كل ما في الباقة المتكاملة' => 'Everything in the Integrated Package',
        'محاسبة لمدة ٦ أشهر' => 'Accounting for 6 months',
        'ضريبة القيمة المضافة والزكاة لمدة ٦ أشهر' => 'VAT and zakat for 6 months',
        'التأمينات الاجتماعية لمدة ٦ أشهر' => 'Social insurance for 6 months',

        // -------------------------------------------------------------
        // EstimatorItemSeeder
        // -------------------------------------------------------------
        'رخصة بلدية أو قطاعية' => 'Municipal or sector licence',
        'فتح حساب بنكي' => 'Bank account opening',
        'العنوان الوطني' => 'National Address',
        'باقة محاسبة سنوية' => 'Annual accounting package',
        'ضريبة القيمة المضافة والزكاة' => 'VAT and zakat',
        'التأمينات الاجتماعية' => 'Social insurance',
        'دراسة جدوى' => 'Feasibility study',

        // -------------------------------------------------------------
        // PageContentSeeder — about
        // -------------------------------------------------------------
        'من نحن' => 'About Us',
        '<p>صنّاع العلامات التجارية بيت خبرة سعودي متخصص في تأسيس الشركات وتمكين الاستثمار في المملكة العربية السعودية. نرافق المستثمر من أول استشارة حتى تشغيل كيانه، ونكمل معه بخدمات ما بعد التأسيس.</p><p>نعمل مع المستثمر المحلي والأجنبي على السواء، ونغطّي المسار كاملاً: اختيار النشاط والكيان، والتراخيص، والسجل التجاري، والعنوان الوطني والحساب البنكي، ثم المحاسبة والضريبة والزكاة والتأمينات بعد الانطلاق.</p><p>رسالتنا أن نجعل رحلة التأسيس واضحة وسريعة وبلا تعقيد: خطوات معلومة، وأسعار معلنة، وفريق يتابع معك حتى تكتمل الإجراءات.</p>' => '<p>Creative Mark is a Saudi advisory firm specialising in company formation and investment enablement in the Kingdom of Saudi Arabia. We accompany the investor from the first consultation through to the entity being operational, and stay on with post-formation services.</p><p>We work with local and foreign investors alike, and we cover the whole route: choosing the activity and the entity, the licences, the Commercial Registration, the National Address and the bank account, then accounting, VAT, zakat and social insurance once operations begin.</p><p>Our purpose is to make the formation journey clear, quick and free of complication: known steps, published prices, and a team that stays with you until the procedures are complete.</p>',
        'من نحن — صنّاع العلامات التجارية' => 'About Us — Creative Mark',
        'بيت خبرة سعودي متخصص في تأسيس الشركات وتمكين الاستثمار في المملكة، من الاستشارة الأولى حتى ما بعد التأسيس.' => 'A Saudi advisory firm specialising in company formation and investment enablement in the Kingdom, from the first consultation through to post-formation.',

        // --- activities ------------------------------------------------
        'الأنشطة' => 'Activities',
        '<p>نؤسّس ونرخّص شركات في كل القطاعات الاقتصادية، ونرشدك إلى النشاط والكيان الأنسب لما تريد أن تعمل فيه. اختيار النشاط ليس تفصيلاً إدارياً — هو ما يحدّد التراخيص المطلوبة ورأس المال والجهة المنظِّمة، ولذلك نبدأ منه دائماً.</p>' => '<p>We form and licence companies across every economic sector, and we guide you to the activity and entity that best fit what you intend to do. Choosing the activity is not an administrative detail — it determines the licences required, the capital and the regulating authority, which is why we always start there.</p>',
        'الأنشطة — صنّاع العلامات التجارية' => 'Activities — Creative Mark',
        'الأنشطة التجارية والصناعية والزراعية والخدمية التي نؤسّس ونرخّص شركات فيها في المملكة.' => 'The commercial, industrial, agricultural and service activities we form and licence companies in across the Kingdom.',
        'تجاري' => 'Commercial',
        'الجملة والتجزئة، والاستيراد والتصدير، والتجارة الإلكترونية، والوكالات والتوزيع.' => 'Wholesale and retail, import and export, e-commerce, and agencies and distribution.',
        'صناعي' => 'Industrial',
        'المصانع والتصنيع والتحويل والتعبئة والتغليف.' => 'Factories, manufacturing, processing, and packing and packaging.',
        'زراعي' => 'Agricultural',
        'الإنتاج الزراعي والثروة الحيوانية والسمكية والتقنيات الزراعية.' => 'Crop production, livestock and fisheries, and agricultural technology.',
        'خدمي' => 'Services',
        'الاستشارات والمقاولات والتقنية والسياحة والضيافة والخدمات اللوجستية.' => 'Consulting, contracting, technology, tourism and hospitality, and logistics.',

        // --- why invest in Saudi Arabia --------------------------------
        'لماذا تستثمر في السعودية' => 'Why Invest in Saudi Arabia',
        '<p>تشهد المملكة تحوّلاً اقتصادياً واسعاً فتح قطاعات جديدة أمام الاستثمار المحلي والأجنبي وبسّط كثيراً من إجراءات التأسيس والترخيص. فيما يلي أبرز ما يجعلها وجهة تستحق الدراسة — والتفاصيل الدقيقة تختلف باختلاف النشاط، فابدأ باستشارة قبل أي قرار.</p>' => '<p>The Kingdom is undergoing a broad economic transformation that has opened new sectors to local and foreign investment and simplified much of the formation and licensing process. What follows are the main reasons it is worth examining — the precise details vary by activity, so start with a consultation before any decision.</p>',
        'لماذا تستثمر في السعودية — صنّاع العلامات التجارية' => 'Why Invest in Saudi Arabia — Creative Mark',
        'أبرز عوامل الجذب الاستثماري في المملكة العربية السعودية للمستثمرين المحليين والأجانب.' => 'The main investment drivers in the Kingdom of Saudi Arabia for local and foreign investors.',
        'اقتصاد متنامٍ' => 'A growing economy',
        'سوق كبير ومتنوّع تدعمه استثمارات واسعة في البنية التحتية والقطاعات غير النفطية.' => 'A large and diversified market, backed by substantial investment in infrastructure and in non-oil sectors.',
        'رؤية 2030' => 'Vision 2030',
        'استراتيجية وطنية تعمل على تنويع الاقتصاد وفتح قطاعات جديدة أمام القطاع الخاص.' => 'A national strategy diversifying the economy and opening new sectors to the private sector.',
        'تملّك أجنبي كامل في كثير من الأنشطة' => 'Full foreign ownership in many activities',
        'تتيح الأنظمة للمستثمر الأجنبي تملّك شركته بالكامل في كثير من الأنشطة دون شريك محلي. الأهلية تختلف بحسب النشاط، ونراجعها معك قبل البدء.' => 'The regulations allow a foreign investor to own their company outright in many activities, with no local partner. Eligibility varies by activity, and we review it with you before starting.',
        'موقع استراتيجي يربط ثلاث قارات' => 'A strategic location joining three continents',
        'تتوسط المملكة آسيا وأفريقيا وأوروبا، وهو ما يمنحها موقعاً لوجستياً للوصول إلى أسواق إقليمية وعالمية.' => 'The Kingdom sits between Asia, Africa and Europe, giving it a logistical position for reaching regional and global markets.',
        'حوافز ومناطق اقتصادية' => 'Incentives and economic zones',
        'برامج ومناطق اقتصادية خاصة تقدّم مزايا للمستثمرين في قطاعات ذات أولوية.' => 'Programmes and special economic zones offering advantages to investors in priority sectors.',

        // --- how we work -----------------------------------------------
        'كيف نعمل' => 'How We Work',
        '<p>مسار واحد واضح لكل عميل، من الاستشارة الأولى حتى تشغيل الكيان. تعرف في كل مرحلة أين وصلت الإجراءات وما المطلوب منك.</p>' => '<p>One clear route for every client, from the first consultation through to the entity being operational. At every stage you know how far the procedures have got and what is needed from you.</p>',
        'كيف نعمل — صنّاع العلامات التجارية' => 'How We Work — Creative Mark',
        'مراحل التأسيس لدى صنّاع العلامات التجارية، من الاستشارة وتحديد النشاط حتى ما بعد التأسيس.' => 'The formation stages at Creative Mark, from the consultation and choice of activity through to post-formation.',
        '١. استشارة وتحديد النشاط والكيان' => '1. Consultation, and settling the activity and entity',
        'نفهم هدفك من الدخول إلى السوق، ونحدّد معك النشاط والشكل القانوني الأنسب والتراخيص التي يفرضها.' => 'We understand your objective in entering the market, and settle with you the most suitable activity and legal form, and the licences these impose.',
        '٢. تجهيز المستندات والتراخيص' => '2. Preparing the documents and licences',
        'نحصر ما يلزم من مستندات وتصديقات، ونجهّز ملفات التراخيص ونقدّمها إلى الجهات المختصة.' => 'We establish which documents and attestations are needed, prepare the licence files and submit them to the competent authorities.',
        '٣. إصدار السجل والتوثيق' => '3. Issuing the registration and notarising',
        'نصدر السجل التجاري ونوثّق عقد التأسيس، ونستكمل العنوان الوطني وملف الحساب البنكي.' => 'We issue the Commercial Registration and notarise the articles of association, and complete the National Address and the bank account file.',
        '٤. ما بعد التأسيس والتشغيل' => '4. Post-formation and operating',
        'نسجّلك لدى الجهات المطلوبة، وندير المحاسبة والإقرارات والتأمينات والتجديدات حتى يعمل الكيان بانتظام.' => 'We register you with the required authorities and run the accounting, the returns, the social insurance and the renewals, so the entity operates in good order.',

        // --- EstimatorGroupSeeder --------------------------------------
        'الخدمات' => 'Services',
        'الاستحواذ على شركة خارجية' => 'Acquiring a company abroad',
        'خدمات أخرى' => 'Other services',

        // --- EstimatorItemSeeder, new lines ----------------------------
        'التصديقات' => 'Attestations',
        'صحيفة الاستثمار (السجل الاستثماري)' => 'Investment record (investment registration)',
        'القوائم المالية' => 'Financial statements',

        // -------------------------------------------------------------
        // CountrySeeder — notes only. Country NAMES keep their English in
        // the seeder: they are proper nouns, not copy, and routing them
        // through a translation dictionary would be pretence.
        // -------------------------------------------------------------
        'سوقنا الأساسي ومحل التأسيس. نؤسّس ونرخّص في الرياض وجدة والدمام وبقية المناطق.' => 'Our home market, and where the formation happens. We form and licence in Riyadh, Jeddah, Dammam and beyond.',
        'نخدم مستثمرين من دبي وأبوظبي والشارقة يوسّعون نشاطهم إلى السوق السعودي.' => 'We serve investors from Dubai, Abu Dhabi and Sharjah expanding into the Saudi market.',
        'مستثمرون وشركات من الدوحة يؤسّسون كياناتهم في المملكة.' => 'Investors and companies from Doha forming their entities in the Kingdom.',
        'نعمل عن بُعد مع المستثمرين في الكويت بنفس المسار والمتابعة.' => 'We work remotely with investors in Kuwait, through the same route and the same follow-up.',
        'تأسيس وتراخيص للمنشآت الصغيرة والمتوسطة القادمة من البحرين.' => 'Formation and licensing for small and medium businesses coming from Bahrain.',
        'مستثمرون من مسقط وخارجها يدخلون السوق السعودي.' => 'Investors from Muscat and beyond entering the Saudi market.',
        'نعمل مع رواد أعمال من القاهرة والإسكندرية يؤسّسون كيانات في المملكة.' => 'We work with founders from Cairo and Alexandria forming entities in the Kingdom.',
        'مستثمرون من عمّان يبحثون عن كيان ورخصة استثمار في السعودية.' => 'Investors from Amman seeking an entity and an investment licence in Saudi Arabia.',

        // -------------------------------------------------------------
        // FaqSeeder
        // -------------------------------------------------------------
        'كم تستغرق إجراءات تأسيس الشركة؟' => 'How long does company formation take?',
        'تختلف المدة باختلاف النشاط وجنسية المستثمر ونوع التراخيص المطلوبة، ولأن جزءاً منها يجري لدى جهات حكومية لا نتحكّم في جداولها. نعطيك تقديراً واقعياً للمدة في الاستشارة الأولى بعد معرفة نشاطك، ونخبرك بما يمكنك تجهيزه مسبقاً لاختصارها.' => 'It varies with the activity, the investor\'s nationality and the licences required, and because part of it sits with government bodies whose schedules we do not control. We give you a realistic estimate at the first consultation once we know your activity, and tell you what you can prepare in advance to shorten it.',

        'هل يمكن للمستثمر الأجنبي أن يتملّك شركته بالكامل؟' => 'Can a foreign investor own their company outright?',
        'نعم في كثير من الأنشطة، وليس في كلها. الأهلية وشروطها تحدّدها الجهات المختصة وتختلف من نشاط إلى آخر، ولذلك نبدأ دائماً بمراجعة أهلية نشاطك تحديداً قبل تجهيز أي مستند.' => 'In many activities yes, though not in all of them. Eligibility and its conditions are set by the competent authorities and differ from one activity to the next, which is why we always begin by reviewing the eligibility of your particular activity before preparing any document.',

        'ما الفرق بين التأسيس والاستحواذ؟' => 'What is the difference between formation and acquisition?',
        'التأسيس يعطيك كياناً جديداً بلا التزامات سابقة، لكن كل ترخيص فيه يبدأ من الصفر. والاستحواذ ينقل إليك كياناً قائماً بسجله وتاريخه — وبالتزاماته أيضاً، ولذلك تسبق مراجعةُ وضعه النظامي والمالي أي توقيع. الأنسب لك يُحسم بعد معرفة نشاطك المستهدف.' => 'Formation gives you a new entity with no prior liabilities, but every licence starts from zero. Acquisition transfers an existing entity to you with its registration and its history — and its liabilities too, which is why a review of its legal and financial standing precedes any signature. Which suits you is settled once we know your target activity.',

        'كم تكلفة التأسيس؟' => 'How much does formation cost?',
        'باقاتنا معلنة: الباقة الأساسية ١٣٬٠٠٠ ريال، والمتكاملة ٢٢٬٠٠٠ ريال، والشاملة ٣٠٬٠٠٠ ريال. تضاف إليها الرسوم الحكومية وأي خدمات إضافية يحتاجها نشاطك. استخدم حاسبة التكلفة لتقدير مبدئي، ويصلك العرض الدقيق بعد الاستشارة.' => 'Our packages are published: the Basic Package at SAR 13,000, the Integrated at SAR 22,000 and the Comprehensive at SAR 30,000. Government fees and any additional services your activity requires are added to these. Use the cost estimator for an initial figure; the precise quote follows the consultation.',

        'ما المستندات المطلوبة للبدء؟' => 'What documents are needed to start?',
        'في الحالة المعتادة: هويات أو جوازات الشركاء، والنشاط المستهدف، وثلاثة أسماء تجارية مقترحة، ورأس المال ونسب الملكية. أما المستثمر الأجنبي فتُطلب منه إضافةً إلى ذلك مستندات الشركة الأم مصدّقة وقوائمها المالية. نرسل لك القائمة الدقيقة بعد الاستشارة، لأنها تختلف بحسب النشاط.' => 'In the usual case: the partners\' ID cards or passports, the target activity, three proposed trade names, and the capital and ownership split. A foreign investor is additionally asked for the parent company\'s attested documents and its financial statements. We send you the precise list after the consultation, because it varies by activity.',

        'هل تفتحون الحساب البنكي أيضاً؟' => 'Do you open the bank account as well?',
        'نجهّز ملف فتح الحساب بالمستندات التي يطلبها البنك ونتابع الطلب معك حتى تفعيل الحساب. قرار الفتح نفسه يعود للبنك ويخضع لإجراءات «اعرف عميلك» لديه، ودورنا أن يصل الملف مكتملاً وصحيحاً من أول مرة.' => 'We prepare the account-opening file with the documents the bank requires and follow the application with you until the account is active. The decision itself rests with the bank and is subject to its Know Your Customer procedures; our part is that the file arrives complete and correct the first time.',

        'ماذا يحدث بعد إصدار السجل التجاري؟' => 'What happens after the Commercial Registration is issued?',
        'يبدأ الكيان التزاماته الدورية: دفاتر محاسبية منتظمة، وإقرارات ضريبة القيمة المضافة والزكاة في مواعيدها، وتسجيل العاملين في التأمينات الاجتماعية، وتجديدات سنوية للسجل والرخص. نتولّى هذه الالتزامات ضمن خدمات ما بعد التأسيس، وهي مشمولة لستة أشهر في الباقة الشاملة.' => 'The entity\'s recurring obligations begin: orderly accounting books, VAT and zakat returns filed on time, employees registered with social insurance, and annual renewals of the registration and the licences. We take these on as part of our post-formation services, and they are included for six months in the Comprehensive Package.',

        'هل تعملون مع مستثمرين من خارج المملكة؟' => 'Do you work with investors from outside the Kingdom?',
        'نعم، وهو جزء أساسي من عملنا. تجري الاستشارة والمتابعة عن بُعد، ونرشدك إلى ما يلزم تصديقه في بلدك قبل إرساله — وهو غالباً أطول جزء في المسار، فالبدء به مبكراً يوفّر وقتاً كبيراً.' => 'Yes, and it is a core part of our work. The consultation and the follow-up run remotely, and we tell you what needs attesting in your own country before it is sent — usually the longest part of the route, so starting it early saves considerable time.',

        'كيف أختار النشاط المناسب؟' => 'How do I choose the right activity?',
        'النشاط يحدّد الشكل القانوني والتراخيص والجهة المنظِّمة، ولذلك نبدأ منه لا من الاسم التجاري. في الاستشارة نراجع ما تنوي عمله فعلياً ونطابقه مع تصنيف النشاط الأدق، لأن نشاطين متشابهين في الاسم قد يختلفان تماماً في الشروط.' => 'The activity determines the legal form, the licences and the regulating authority, which is why we start there rather than with the trade name. In the consultation we go through what you actually intend to do and match it to the most precise activity classification, because two activities with similar names can differ entirely in their conditions.',

        'كيف أبدأ؟' => 'How do I get started?',
        'احجز استشارة عبر الموقع. نحدّد فيها النشاط والكيان المناسبين ونراجع أهليتك، ثم نرسل لك عرضاً واضحاً بالخطوات والمستندات والتكلفة.' => 'Book a consultation through the site. In it we settle the right activity and entity and review your eligibility, then send you a clear quote covering the steps, the documents and the cost.',

        // -------------------------------------------------------------
        // ArticleSeeder
        // -------------------------------------------------------------
        'دليل تأسيس شركة ذات مسؤولية محدودة خطوة بخطوة' => 'A Step-by-Step Guide to Forming a Limited Liability Company',
        'المسار الكامل من اختيار النشاط حتى إصدار السجل التجاري، وما الذي يؤخّره عادةً.' => 'The full route from choosing the activity to the Commercial Registration being issued — and what usually holds it up.',

        'الفرق بين التأسيس والاستحواذ' => 'Formation or Acquisition: What Is the Difference?',
        'مساران يصلان بك إلى كيان يحمل اسمك، ولكل منهما تكلفة ومخاطر ومدة مختلفة.' => 'Two routes to an entity in your name, each with a different cost, risk profile and timeline.',

        'كيف يحصل المستثمر الأجنبي على رخصة الاستثمار' => 'How a Foreign Investor Obtains an Investment Licence',
        'ما هي الرخصة، ومتى تُطلب، وما الذي يجهّزه المستثمر قبل التقديم.' => 'What the licence is, when it is required, and what an investor prepares before applying.',

        'الأنشطة المسموح فيها بالتملّك الأجنبي الكامل' => 'Activities Open to Full Foreign Ownership',
        'التملّك الكامل متاح في كثير من الأنشطة لا في كلها — وكيف تعرف أين يقع نشاطك.' => 'Full ownership is available in many activities but not all — and how to find out where yours falls.',

        'ما بعد التأسيس: الضرائب والزكاة والتأمينات' => 'After Formation: Tax, Zakat and Social Insurance',
        'الالتزامات الدورية التي تبدأ من لحظة صدور السجل، ولماذا التأخر فيها مكلف.' => 'The recurring obligations that begin the moment the registration is issued, and why falling behind on them is expensive.',

        // --- article bodies --------------------------------------------
        '<p>تأسيس شركة ذات مسؤولية محدودة في المملكة مسار معلوم الخطوات. ما يصنع الفرق في مدته ليس الإجراءات نفسها، بل ترتيبها وجاهزية المستندات قبل الدخول فيها.</p><h2>ابدأ من النشاط، لا من الاسم</h2><p>النشاط الذي تختاره يحدّد الشكل القانوني والتراخيص المطلوبة والجهة المنظِّمة. كثيرون يبدأون باختيار اسم تجاري ثم يكتشفون أن النشاط الذي يريدونه يفرض ترخيصاً إضافياً أو شكلاً قانونياً مختلفاً. اختيار النشاط أولاً يوفّر تعديلاً لاحقاً على السجل والعقد معاً.</p><h2>الخطوات بالترتيب</h2><p>حجز الاسم التجاري، ثم صياغة عقد التأسيس وتوثيقه، ثم إصدار السجل التجاري. بعد السجل تأتي التسجيلات التي لا يكتمل التشغيل بدونها: العنوان الوطني، والغرفة التجارية، والتسجيل الضريبي، والتأمينات الاجتماعية، ثم ملف فتح الحساب البنكي.</p><h2>ما الذي يؤخّر الملفات عادةً</h2><p>أكثر ما يؤخّر التأسيس ليس الإجراء بل الورق: جواز قارب على الانتهاء، أو تصديق ناقص على مستند صادر من خارج المملكة، أو اسم تجاري مرفوض بلا بديل جاهز. تجهيز ثلاثة أسماء مقترحة، ومراجعة سريان كل مستند وتصديقه قبل التقديم، يختصر وقتاً أطول مما يبدو.</p><h2>وبعد السجل</h2><p>إصدار السجل ليس نهاية المسار. الكيان يصبح من لحظته مسؤولاً عن إقرارات دورية وتجديدات سنوية، والتأخر فيها يُعالَج بغرامات لا بتنبيهات. من المفيد أن تعرف هذه الالتزامات قبل التأسيس لا بعده.</p>' => '<p>Forming a limited liability company in the Kingdom follows a known set of steps. What makes the difference to how long it takes is not the procedures themselves but their order, and how ready the documents are before you enter them.</p><h2>Start from the activity, not the name</h2><p>The activity you choose determines the legal form, the licences required and the regulating authority. Many people start by choosing a trade name and then discover that the activity they want imposes an additional licence or a different legal form. Choosing the activity first saves you amending both the registration and the articles later.</p><h2>The steps, in order</h2><p>Reserving the trade name, then drafting and notarising the articles of association, then issuing the Commercial Registration. After the registration come the registrations without which operations are not complete: the National Address, the chamber of commerce, tax registration and social insurance, and then the bank account file.</p><h2>What usually holds a file up</h2><p>What delays formation is rarely the procedure — it is the paperwork: a passport close to expiry, an incomplete attestation on a document issued outside the Kingdom, or a rejected trade name with no alternative ready. Preparing three proposed names, and checking that every document is valid and attested before submission, saves more time than it appears to.</p><h2>And after the registration</h2><p>Issuing the registration is not the end of the route. From that moment the entity is responsible for recurring returns and annual renewals, and falling behind on them is met with penalties rather than reminders. It is worth knowing these obligations before formation rather than after.</p>',

        '<p>حين يريد مستثمر كياناً في المملكة أمامه طريقان: أن يؤسّس شركة جديدة، أو أن يستحوذ على شركة قائمة وينقل ملكيتها إليه. الاختيار بينهما ليس مسألة تفضيل، بل مسألة ما الذي تحتاجه فعلاً.</p><h2>التأسيس: تبدأ من صفحة بيضاء</h2><p>تحصل على كيان بلا تاريخ: لا التزامات سابقة، ولا نزاعات، ولا دفاتر تحتاج مراجعة. وتحدّد النشاط والاسم وهيكل الملكية كما تريدهم من البداية. المقابل أن كل ترخيص يبدأ من نقطة الصفر.</p><h2>الاستحواذ: تشتري ما هو قائم</h2><p>الاستحواذ منطقي حين يكون للكيان القائم شيء تحتاجه ولا يُنشأ بسرعة: سجل قديم، أو ترخيص يصعب الحصول عليه لنشاط معيّن، أو تاريخ تشغيلي يُطلب في التعاقدات. لكنك تشتري الكيان بكل ما فيه، بما في ذلك التزاماته السابقة.</p><h2>ولذلك تسبق المراجعة الشراء</h2><p>القاعدة العملية أن أي استحواذ يبدأ بمراجعة الوضع النظامي والالتزامات المالية والضريبية للكيان قبل توقيع أي شيء. المبلغ الذي تدفعه ثمناً للشركة ليس التكلفة الحقيقية إن كانت عليها مستحقات لم تُراجَع.</p><h2>أيهما أنسب لك</h2><p>إن كان نشاطك مفتوحاً وإجراءاته مباشرة، فالتأسيس عادةً أوضح وأقل مخاطرة. وإن كان النشاط مقيّداً أو يتطلب سجلاً قائماً، فالاستحواذ يوفّر وقتاً يصعب توفيره بغيره. الإجابة تُحسم في الاستشارة الأولى بعد معرفة النشاط المستهدف.</p>' => '<p>An investor who wants an entity in the Kingdom has two routes: form a new company, or acquire an existing one and transfer its ownership. Choosing between them is not a matter of preference but of what you actually need.</p><h2>Formation: you start from a blank page</h2><p>You get an entity with no history: no prior liabilities, no disputes, no books needing review. And you set the activity, the name and the ownership structure exactly as you want them from the outset. The trade-off is that every licence starts from zero.</p><h2>Acquisition: you buy what already exists</h2><p>Acquisition makes sense when the existing entity holds something you need that cannot be created quickly: an older registration, a licence that is difficult to obtain for a particular activity, or an operating history that contracts ask for. But you buy the entity with everything in it, its prior liabilities included.</p><h2>Which is why review precedes purchase</h2><p>The practical rule is that any acquisition begins with a review of the entity\'s legal standing and its financial and tax liabilities, before anything is signed. The sum you pay for the company is not the real cost if it carries dues nobody examined.</p><h2>Which suits you</h2><p>If your activity is open and its procedures are direct, formation is usually clearer and carries less risk. If the activity is restricted or requires an existing registration, acquisition saves time that is hard to save any other way. The answer is settled at the first consultation, once the target activity is known.</p>',

        '<p>رخصة الاستثمار هي ما يتيح للمستثمر غير السعودي أن يمارس نشاطاً في المملكة باسمه. هي أول محطة في مسار المستثمر الأجنبي، وما يليها من إجراءات — السجل التجاري والتسجيلات — يأتي بعدها لا قبلها.</p><h2>ابدأ بسؤال الأهلية</h2><p>ليست كل الأنشطة مفتوحة بالدرجة نفسها، وشروط كل نشاط تختلف. لذلك فإن السؤال الأول ليس «ما المستندات المطلوبة» بل «هل النشاط الذي أستهدفه مؤهّل، وبأي شروط». تجهيز ملف كامل لنشاط غير مؤهّل هو أكثر أخطاء هذا المسار كلفةً.</p><h2>ما يُطلب عادةً</h2><p>في الحالة المعتادة للمستثمر الاعتباري: مستندات الشركة الأم موثّقة ومصدّقة حسب الأصول، وقوائمها المالية، وجوازات الملاك، ووصف واضح للنشاط المستهدف داخل المملكة. القائمة الدقيقة تختلف بحسب النشاط وجنسية المستثمر وهيكل الملكية.</p><h2>التصديق هو ما يستهلك الوقت</h2><p>المستندات الصادرة من خارج المملكة تحتاج سلسلة تصديقات، وهي غالباً أطول جزء في المسار لأنها تجري في بلد المنشأ. البدء بها مبكراً — بالتوازي مع مراجعة الأهلية لا بعدها — هو أكثر ما يختصر المدة الكلية.</p><h2>وبعد صدور الرخصة</h2><p>الرخصة تفتح الباب ولا تُنهي الطريق: يليها عقد التأسيس والسجل التجاري والعنوان الوطني والحساب البنكي، ثم التسجيلات الدورية. من المفيد النظر إلى المسار كاملاً من البداية بدل التعامل مع كل محطة على حدة.</p>' => '<p>The investment licence is what allows a non-Saudi investor to carry on an activity in the Kingdom in their own name. It is the first stop on the foreign investor\'s route, and what follows it — the Commercial Registration and the registrations — comes after it, not before.</p><h2>Start with the eligibility question</h2><p>Not every activity is open to the same degree, and each activity\'s conditions differ. So the first question is not "which documents are required" but "is the activity I am targeting eligible, and on what conditions". Preparing a complete file for an ineligible activity is the costliest mistake on this route.</p><h2>What is usually asked for</h2><p>In the usual case of a corporate investor: the parent company\'s documents, notarised and duly attested, its financial statements, the owners\' passports, and a clear description of the target activity inside the Kingdom. The precise list varies with the activity, the investor\'s nationality and the ownership structure.</p><h2>Attestation is what consumes the time</h2><p>Documents issued outside the Kingdom need a chain of attestations, and this is usually the longest part of the route because it happens in the country of origin. Starting it early — alongside the eligibility review rather than after it — is what shortens the overall timeline most.</p><h2>And once the licence is issued</h2><p>The licence opens the door; it does not end the road. It is followed by the articles of association, the Commercial Registration, the National Address and the bank account, then the recurring registrations. It helps to look at the whole route from the start rather than treating each stop separately.</p>',

        '<p>من أكثر ما يُسأل عنه المستثمر الأجنبي: هل يمكنني تملّك شركتي بالكامل دون شريك سعودي؟ الإجابة القصيرة: في كثير من الأنشطة نعم، وليس في كلها.</p><h2>لماذا لا توجد قائمة نهائية هنا</h2><p>الأنشطة المفتوحة وشروط كل منها تحدّدها الجهات المختصة، وتُحدَّث من وقت لآخر. أي قائمة تُنشر في مقال تصبح مرجعاً يُعتمد عليه، وتصير خاطئة بصمت عند أول تحديث. لذلك لا نضع هنا قائمة، بل نوضّح كيف يُعرف الجواب.</p><h2>كيف تُعرف حالة نشاطك</h2><p>الأمر يُحسم بتحديد النشاط بدقة — لا «تجارة» بل النشاط بمسماه وتصنيفه — ثم مراجعة وضعه لدى الجهة المختصة. نشاطان يبدوان متشابهين قد يختلفان تماماً في شروط التملّك.</p><h2>الشروط ليست الملكية وحدها</h2><p>حتى داخل الأنشطة المفتوحة قد ترتبط بالنشاط شروط أخرى: رأس مال معيّن، أو خبرة سابقة، أو ترخيص من جهة قطاعية. النظر إلى الملكية وحدها يعطي صورة ناقصة عن جاهزية المشروع.</p><h2>الخطوة العملية</h2><p>قبل تجهيز أي مستند، اطلب مراجعة أهلية لنشاطك تحديداً. هي أقصر خطوة في المسار وأكثرها توفيراً للوقت، لأنها تحدّد ما إذا كان الباقي ممكناً أصلاً وبأي شكل.</p>' => '<p>One of the questions foreign investors ask most: can I own my company outright, with no Saudi partner? The short answer is that in many activities you can, and in some you cannot.</p><h2>Why there is no definitive list here</h2><p>Which activities are open, and on what conditions, is set by the competent authorities and updated from time to time. Any list published in an article becomes something people rely on, and becomes silently wrong at the first update. So rather than a list, what follows is how the answer is found.</p><h2>How to establish where your activity stands</h2><p>It is settled by defining the activity precisely — not "trade" but the activity by its name and classification — and then checking its status with the competent authority. Two activities that look similar can differ entirely in their ownership conditions.</p><h2>Ownership is not the only condition</h2><p>Even within open activities, other conditions may attach: a particular capital level, prior experience, or a licence from a sector regulator. Looking at ownership alone gives an incomplete picture of whether the project is ready.</p><h2>The practical step</h2><p>Before preparing any document, ask for an eligibility review of your specific activity. It is the shortest step on the route and the one that saves the most time, because it establishes whether the rest is possible at all, and in what form.</p>',

        '<p>ينتهي كثير من المستثمرين من التأسيس بشعور أن العمل الورقي انتهى. في الواقع هو يبدأ عندها: الكيان القائم عليه التزامات دورية تبدأ من لحظة وجوده، لا من لحظة أول إيراد.</p><h2>الدفاتر أولاً</h2><p>كل ما يلي يعتمد على وجود دفاتر محاسبية منتظمة. الإقرارات تُبنى على أرقام، والأرقام تأتي من قيود مسجّلة أولاً بأول. محاولة تجميع سنة كاملة قبل موعد الإقرار بأسبوع هي مصدر معظم الأخطاء في هذه المرحلة.</p><h2>ضريبة القيمة المضافة والزكاة</h2><p>لكل منهما تسجيل وإقرارات ومواعيد. تفاصيل ما ينطبق على كيانك — ومتى يبدأ — تعتمد على نشاطك وإيراداتك وهيكل ملكيته، وتُحدَّد عند التسجيل لا بالتقدير.</p><h2>التأمينات الاجتماعية</h2><p>تسجيل المنشأة وتسجيل العاملين فيها التزام مستقل عن الالتزام الضريبي، ويبدأ مع أول موظف. تأخير التسجيل لا يؤجّل الاستحقاق.</p><h2>لماذا التأخر مكلف</h2><p>التأخر في إقرار أو تجديد لا يُعالَج بتنبيه غالباً، بل بغرامة، وقد يصل إلى تعليق قدرة الكيان على إنهاء معاملاته. وهي التكلفة الوحيدة في هذا المسار التي يمكن تفاديها بالكامل بالتنظيم وحده.</p>' => '<p>Many investors finish forming a company with the feeling that the paperwork is over. In fact it starts there: a live entity carries recurring obligations that begin the moment it exists, not the moment of its first revenue.</p><h2>The books come first</h2><p>Everything that follows depends on orderly accounting books. Returns are built from figures, and figures come from entries recorded as they happen. Trying to assemble a whole year in the week before a filing deadline is the source of most errors at this stage.</p><h2>VAT and zakat</h2><p>Each has its own registration, returns and deadlines. Exactly what applies to your entity — and when it begins — depends on your activity, your revenue and your ownership structure, and is established at registration rather than by estimate.</p><h2>Social insurance</h2><p>Registering the establishment and registering its employees is an obligation separate from the tax one, and it begins with the first employee. Delaying the registration does not delay the liability.</p><h2>Why falling behind is expensive</h2><p>A late return or renewal is usually met with a penalty rather than a reminder, and can extend to suspending the entity\'s ability to complete its transactions. It is the one cost on this route that organisation alone can avoid entirely.</p>',
    ];

    /**
     * Pages whose Arabic copy makes claims about Saudi regulation —
     * foreign-ownership limits, licensing, tax and zakat obligations.
     *
     * Their English translations are exactly as provisional as the Arabic
     * originals and carry the same need for professional review. Reported
     * at the end of every run so a reader never mistakes "translated" for
     * "verified".
     *
     * @var list<string>
     */
    private array $regulatoryClaimSlugs = [
        'why-invest-saudi-arabia',
        'activities',
    ];

    private HtmlSanitizerService $sanitizer;

    private int $totalTranslated = 0;

    private int $totalSkippedExisting = 0;

    /** @var list<string> */
    private array $untranslatable = [];

    public function handle(HtmlSanitizerService $sanitizer): int
    {
        /*
         * Laravel reuses one resolved Command instance across repeated
         * Artisan::call() invocations in a single process — the seeder
         * calls this, and a test may call it again straight afterwards.
         * Reset explicitly rather than assuming a fresh object.
         */
        $this->totalTranslated = 0;
        $this->totalSkippedExisting = 0;
        $this->untranslatable = [];

        $force = (bool) $this->option('force');

        $summary = [];

        $this->sanitizer = $sanitizer;

        $summary['Page'] = $this->translateModel(Page::class, ['title', 'body', 'meta_title', 'meta_description'], $force, htmlFields: ['body']);
        $summary['PageSection'] = $this->translatePageSections($force);
        $summary['Service'] = $this->translateModel(Service::class, ['name', 'summary', 'body', 'requirements', 'process'], $force, htmlFields: ['body']);
        $summary['Package'] = $this->translatePackages($force);
        $summary['EstimatorGroup'] = $this->translateModel(EstimatorGroup::class, ['label'], $force);
        $summary['EstimatorItem'] = $this->translateModel(EstimatorItem::class, ['label'], $force);
        $summary['Country'] = $this->translateModel(Country::class, ['name', 'notes'], $force);
        $summary['Faq'] = $this->translateModel(Faq::class, ['question', 'answer'], $force);
        $summary['Article'] = $this->translateModel(Article::class, ['title', 'excerpt', 'body'], $force, htmlFields: ['body']);
        $summary['Testimonial'] = $this->translateModel(Testimonial::class, ['quote'], $force);
        $summary['SeoMeta'] = $this->translateModel(SeoMeta::class, ['meta_title', 'meta_description'], $force);

        $this->printSummary($summary);

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $fields
     * @param  list<string>  $htmlFields
     * @return array<string, int>
     */
    private function translateModel(string $modelClass, array $fields, bool $force, array $htmlFields = []): array
    {
        $stats = $this->emptyStats();

        foreach ($modelClass::all() as $model) {
            $dirty = false;

            foreach ($fields as $field) {
                // false: the raw Arabic with no fallback. With the
                // fallback on, a field that has NO Arabic would read as
                // whatever locale answered, and we would translate a
                // string the source never contained.
                $ar = $model->getTranslation($field, 'ar', false);

                if (empty($ar)) {
                    continue;
                }

                if (! empty($model->getTranslation($field, 'en', false)) && ! $force) {
                    $stats['skipped_existing']++;

                    continue;
                }

                $en = $this->translations[$ar] ?? null;

                if ($en === null) {
                    $stats['skipped_no_translation']++;
                    $this->untranslatable[] = class_basename($modelClass).'#'.$model->getKey()." [{$field}]: ".Str::limit($ar, 60);

                    continue;
                }

                if (in_array($field, $htmlFields, true)) {
                    $en = $this->sanitizer->sanitizeRichText($en);
                }

                $model->setTranslation($field, 'en', $en);
                $dirty = true;
                $stats['translated']++;
            }

            if ($dirty) {
                $model->save();
            }
        }

        $this->tally($stats);

        return $stats;
    }

    /**
     * Packages carry a translatable name and description AND a `features`
     * JSON array whose every element is its own {ar, en} pair — a shape
     * spatie/laravel-translatable knows nothing about, so it is walked by
     * hand here.
     *
     * @return array<string, int>
     */
    private function translatePackages(bool $force): array
    {
        $stats = $this->translateModel(Package::class, ['name', 'description'], $force);

        foreach (Package::all() as $package) {
            $features = $package->features;

            if (! is_array($features)) {
                continue;
            }

            $dirty = false;

            foreach ($features as $index => $feature) {
                $ar = $feature['ar'] ?? null;

                if (! is_string($ar) || $ar === '') {
                    continue;
                }

                if (! empty($feature['en']) && ! $force) {
                    $stats['skipped_existing']++;
                    $this->totalSkippedExisting++;

                    continue;
                }

                $en = $this->translations[$ar] ?? null;

                if ($en === null) {
                    $stats['skipped_no_translation']++;
                    $this->untranslatable[] = "Package#{$package->getKey()} [features.{$index}]: ".Str::limit($ar, 60);

                    continue;
                }

                $features[$index]['en'] = $en;
                $dirty = true;
                $stats['translated']++;
                $this->totalTranslated++;
            }

            if ($dirty) {
                $package->features = $features;
                $package->save();
            }
        }

        return $stats;
    }

    /**
     * PageSection.content is a single JSON blob holding title/description/
     * icon per locale, not a spatie translatable field — there is no
     * getTranslation()/setTranslation() to call, so the array is read and
     * written directly.
     *
     * @return array<string, int>
     */
    private function translatePageSections(bool $force): array
    {
        $stats = $this->emptyStats();

        foreach (PageSection::all() as $section) {
            $content = $section->content;
            $dirty = false;

            foreach (['title', 'description'] as $field) {
                $ar = $content[$field]['ar'] ?? null;

                if (empty($ar)) {
                    continue;
                }

                if (! empty($content[$field]['en']) && ! $force) {
                    $stats['skipped_existing']++;

                    continue;
                }

                $en = $this->translations[$ar] ?? null;

                if ($en === null) {
                    $stats['skipped_no_translation']++;
                    $this->untranslatable[] = 'PageSection#'.$section->getKey()." [{$field}]: ".Str::limit($ar, 60);

                    continue;
                }

                $content[$field]['en'] = $en;
                $dirty = true;
                $stats['translated']++;
            }

            if ($dirty) {
                $section->content = $content;
                $section->save();
            }
        }

        $this->tally($stats);

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    private function emptyStats(): array
    {
        return ['translated' => 0, 'skipped_existing' => 0, 'skipped_no_translation' => 0];
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function tally(array $stats): void
    {
        $this->totalTranslated += $stats['translated'];
        $this->totalSkippedExisting += $stats['skipped_existing'];
    }

    /**
     * @param  array<string, array<string, int>>  $summary
     */
    private function printSummary(array $summary): void
    {
        $this->newLine();
        $this->info('English translation summary:');

        $this->table(
            ['Model', 'Translated', 'Skipped (already English)', 'Skipped (no translation available)'],
            collect($summary)->map(fn (array $stats, string $model) => [
                $model,
                $stats['translated'],
                $stats['skipped_existing'],
                $stats['skipped_no_translation'],
            ])->values()->all()
        );

        if ($this->untranslatable !== []) {
            $this->newLine();
            $this->warn('Arabic content with no entry in this command\'s dictionary. These need authoring, not guessing:');

            foreach ($this->untranslatable as $line) {
                $this->line("  - {$line}");
            }
        }

        $this->newLine();
        $this->line("Total fields translated: {$this->totalTranslated}");
        $this->line("Total fields that already had English (skipped): {$this->totalSkippedExisting}");

        $this->newLine();
        $this->warn(
            'Reminder: the copy on ['.implode(', ', $this->regulatoryClaimSlugs).'] describes Saudi '
            .'regulation — ownership rules, licensing, tax and zakat. The English here is a translation '
            .'of that copy, not a verification of it, and is exactly as provisional as the Arabic.'
        );
    }
}
