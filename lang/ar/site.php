<?php

/**
 * Every static (non-database) UI string on the public site.
 *
 * Content that an admin edits — services, packages, articles, page copy —
 * lives in the database as translatable JSON columns, not here. This file
 * is only for chrome: navigation, labels, button text and the fixed
 * headings around dynamic content.
 */
return [

    'brand' => [
        'name' => 'صنّاع العلامات التجارية',
        'name_latin' => 'Creative Mark',
        'tagline' => 'نبني العلامات التجارية التي تُذكر',
    ],

    'nav' => [
        'breadcrumb' => 'مسار التنقل',
        'home' => 'الرئيسية',
        'skip_to_content' => 'تخطَّ إلى المحتوى الرئيسي',
        'open_menu' => 'فتح القائمة',
        'close_menu' => 'إغلاق القائمة',
        'menu_dialog_label' => 'قائمة التنقل',
        'back_to_top' => 'العودة إلى الأعلى',
        'switch_to_english' => 'English',
        'switch_to_arabic' => 'العربية',
        'switch_language_label' => 'تغيير اللغة',
        'book_consultation' => 'احجز استشارة',
    ],

    'hero' => [
        'eyebrow' => 'استوديو هوية وعلامات تجارية · السعودية',
        'title_before' => 'نصنع',
        'title_highlight' => 'علامتك',
        'title_after' => 'ونبنيها لتبقى',
        'lead' => 'من الاستراتيجية إلى الهوية البصرية الكاملة — نحوّل ما تمثّله علامتك إلى نظام بصري متماسك يعمل في كل مكان تظهر فيه.',
        'cta_primary' => 'احجز استشارة',
        'cta_secondary' => 'احسب تكلفتك',
        'scroll_cue' => 'مرّر لأسفل',
        // The decorative sky/skyline is announced once, so a screen reader
        // is not left with an unexplained canvas element.
        // Labels for the hero figures. The VALUES are editable
        // settings (seeded empty); these labels are chrome.
        'stats' => [
            'companies' => 'مشروع منجز',
            'turnaround' => 'متوسط مدة الإنجاز',
            'satisfaction' => 'رضا العملاء',
        ],
        'background_label' => 'خلفية توضيحية: أفق مدينة عند الغروب',
    ],

    // إشعار ملفات تعريف الارتباط. كلا الزرين إجابة حقيقية: الرفض يُحفظ
    // كي لا يُسأل الزائر مرة أخرى، وتجاهل الإشعار يُعامل كالرفض تماماً على
    // الخادم.
    'consent' => [
        'aria_label' => 'إشعار ملفات تعريف الارتباط',
        'message' => 'نسجّل تنقّل الزوار داخل الموقع ونستخدم أدوات إعلانية، بموافقتك فقط. لا يُسجَّل شيء قبل موافقتك، ولا نحفظ عنوان IP كاملاً إطلاقاً.',
        'policy_link' => 'اطّلع على سياسة الخصوصية',
        'accept' => 'أوافق',
        'decline' => 'أرفض',
    ],

    'marquee' => [
        'label' => 'خدماتنا',
    ],

    'services' => [
        'label' => 'خدماتنا',
        'read_more' => 'اقرأ التفاصيل',
        'requirements' => 'ما نحتاجه منك',
        'process' => 'خطوات العمل',
        'enquire' => 'اطلب هذه الخدمة',
        'other' => 'خدمات أخرى',
        'eyebrow' => 'ما نقدّمه',
        'title' => 'خدمة مبنية على فهم، لا على ذوق',
        'lead' => 'نبدأ بالسؤال قبل التصميم، ونسلّم نظاماً كاملاً يستطيع فريقك تطبيقه بثقة.',
        'view_all' => 'كل الخدمات',
        'empty' => 'لم تُضَف أي خدمات بعد.',
    ],

    'estimator' => [
        'eyebrow' => 'حاسبة تفاعلية',
        'title' => 'احسب تكلفة مشروعك في ثوانٍ',
        'lead' => 'اختر ما تحتاجه من بنود، واحصل على تقدير مبدئي فوري — من غير ما تتواصل مع أحد.',
        'cta' => 'افتح الحاسبة',
        'panel_title' => 'تقدير مبدئي',
        'total_label' => 'التكلفة التقديرية',
        'currency' => 'ر.س',
        'disclaimer' => 'تقدير إرشادي غير ملزم. العرض الرسمي يصلك بعد جلسة الاستشارة.',
        // Shown when no estimator item has been priced and activated yet,
        // so the teaser never invents a number.
        'unpriced' => 'الأسعار قيد الإعداد',
        'unpriced_hint' => 'تواصل معنا للحصول على عرض سعر مفصّل لمشروعك.',
    ],

    'packages' => [
        'enquiry_heading' => 'طلب الباقة :package',
        'enquiry_lead' => 'اترك بياناتك وسنتواصل معك بتفاصيل الباقة.',
        'back' => 'رجوع إلى الباقات',
        'label' => 'الباقات',
        'compare' => 'مقارنة الباقات',
        'feature' => 'البند',
        'included' => 'مشمول',
        'not_included' => 'غير مشمول',
        'eyebrow' => 'باقاتنا',
        'title' => 'اختر الباقة التي تناسب مرحلتك',
        'lead' => 'نطاقات واضحة ومخرجات محدّدة. قارن واختر، أو اطلب نطاقاً مخصّصاً.',
        'popular' => 'الأكثر طلباً',
        'price_on_request' => 'على الطلب',
        'currency' => 'ر.س',
        'choose' => 'اطلب هذه الباقة',
        'view_all' => 'كل الباقات',
        'empty' => 'لم تُضَف أي باقات بعد.',
    ],

    'tracker' => [
        'eyebrow' => 'بوابة العميل',
        'title' => 'تابع مشروعك خطوة بخطوة',
        'lead' => 'كل طلب له رابط متابعة خاص، تشوف فيه المرحلة الحالية ونسبة الإنجاز وآخر تحديث.',
        'cta' => 'تتبّع طلبك',
        'steps' => [
            ['title' => 'تقديم الطلب', 'body' => 'تختار الباقة أو النطاق وتبدأ.'],
            ['title' => 'العمل والمراجعة', 'body' => 'نشتغل على المراحل ونشاركك كل مرحلة للمراجعة.'],
            ['title' => 'التسليم', 'body' => 'تستلم الملفات ودليل الهوية كاملاً.'],
        ],
        'sample_reference' => 'طلب #CM-XXXX',
        'sample_status' => 'قيد التنفيذ',
        'sample_note' => 'مثال توضيحي لشكل شاشة المتابعة.',
        'progress_label' => 'نسبة الإنجاز',
    ],

    'booking' => [
        'title' => 'جاهز تبدأ علامتك؟',
        'lead' => 'احجز جلسة استشارة، ونرسم معك الطريق من الفكرة إلى هوية كاملة.',
        'cta' => 'احجز موعدك',
    ],

    'testimonials' => [
        'eyebrow' => 'آراء العملاء',
        'title' => 'ماذا قالوا عن العمل معنا',
    ],

    'faq' => [
        'label' => 'الأسئلة الشائعة',
        'lead' => 'إجابات على ما نُسأل عنه أكثر من غيره. إن لم تجد سؤالك، تواصل معنا.',
        'empty' => 'لا توجد أسئلة بعد.',
        'eyebrow' => 'أسئلة شائعة',
        'title' => 'أسئلة نُسأل عنها كثيراً',
        'view_all' => 'كل الأسئلة',
    ],

    'articles' => [
        'label' => 'المدونة',
        'empty' => 'لا توجد مقالات منشورة بعد.',
        'related' => 'مقالات ذات صلة',
        'eyebrow' => 'من المدونة',
        'title' => 'أفكار عن العلامات التجارية',
        'read_more' => 'اقرأ المقال',
        'view_all' => 'كل المقالات',
    ],

    'errors' => [
        'back_home' => 'العودة إلى الرئيسية',
        'not_found' => ['title' => 'الصفحة غير موجودة', 'message' => 'الرابط الذي فتحته لم يعد موجوداً، أو ربما كُتب بشكل غير صحيح.'],
        'server_error' => ['title' => 'حدث خطأ غير متوقع', 'message' => 'المشكلة من طرفنا وقد سُجّلت. جرّب مرة أخرى بعد قليل.'],
        'forbidden' => ['title' => 'لا تملك صلاحية الوصول', 'message' => 'هذه الصفحة مخصّصة لفريق العمل فقط.'],
        'expired' => ['title' => 'انتهت صلاحية الجلسة', 'message' => 'حدّث الصفحة وأعد المحاولة.'],
        'maintenance' => ['title' => 'الموقع تحت الصيانة', 'message' => 'نجري تحديثاً سريعاً. سنعود خلال دقائق.'],
    ],

    'countries' => [
        'label' => 'الأسواق',
        'eyebrow' => 'أين نعمل',
        'title' => 'الأسواق التي نخدمها',
        'lead' => 'نعمل مع عملاء في السعودية والخليج والشرق الأوسط.',
        'empty' => 'لم تُضَف أي أسواق بعد.',
    ],

    'contact' => [
        'label' => 'اتصل بنا',
        'eyebrow' => 'تواصل',
        'title' => 'تواصل معنا',
        'lead' => 'أخبرنا عن مشروعك ونعود إليك بخطة واضحة.',
        'whatsapp' => 'واتساب',
        'phone' => 'الهاتف',
        'email' => 'البريد الإلكتروني',
        'address' => 'العنوان',
        'social' => 'تابعنا',
        'no_details' => 'بيانات التواصل قيد الإعداد.',
        'form_coming' => 'نموذج الإرسال قيد التجهيز. حتى ذلك الحين، استخدم أياً من وسائل التواصل أعلاه.',
    ],

    'comments' => [
        'form_heading' => 'أضف تعليقاً',
        'moderation_note' => 'تُراجَع التعليقات قبل نشرها.',
        'heading' => '{count} تعليق|{1} تعليق واحد|[2,10] :count تعليقات|[11,*] :count تعليقاً',
    ],

    'forms' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الهاتف',
        'message' => 'رسالتك',
        'comment' => 'تعليقك',
        'consent' => 'الموافقة',
        'required' => 'مطلوب',
        'optional' => 'اختياري',
        'submit' => 'إرسال',
        'honeypot_label' => 'اترك هذا الحقل فارغاً',
        'errors_heading' => 'يرجى مراجعة ما يلي:',
        'consent_label' => 'أوافق على حفظ بياناتي للتواصل معي بخصوص استفساري.',
        'consent_note' => 'لن نشارك بياناتك مع أي جهة أخرى. اطّلع على :policy.',
        'heading' => 'أرسل لنا رسالة',
        'lead' => 'املأ النموذج وسنعود إليك في أقرب وقت.',
    ],

    'flash' => [
        'contact_submitted' => 'شكراً لك. وصلتنا رسالتك وسنعود إليك قريباً.',
        'comment_submitted' => 'شكراً لك. سيظهر تعليقك بعد مراجعته.',
        'package_enquiry_submitted' => 'شكراً لك. وصلنا طلبك وسنتواصل معك بتفاصيل الباقة.',
    ],

    'footer' => [
        'about' => 'استوديو متخصص في بناء الهويات البصرية واستراتيجيات العلامات التجارية في السعودية والخليج.',
        'links_about' => 'من نحن',
        'links_process' => 'كيف نعمل',
        'links_heading' => 'روابط',
        'contact_heading' => 'تواصل',
        'legal_heading' => 'قانوني',
        'rights' => 'جميع الحقوق محفوظة.',
        'privacy_link' => 'سياسة الخصوصية',
        'terms_link' => 'الشروط والأحكام',
    ],
];
