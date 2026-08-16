<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Country;
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
