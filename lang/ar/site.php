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
        'background_label' => 'خلفية توضيحية: أفق مدينة عند الغروب',
    ],

    'marquee' => [
        'label' => 'خدماتنا',
    ],

    'services' => [
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
        'eyebrow' => 'أسئلة شائعة',
        'title' => 'أسئلة نُسأل عنها كثيراً',
        'view_all' => 'كل الأسئلة',
    ],

    'articles' => [
        'eyebrow' => 'من المدونة',
        'title' => 'أفكار عن العلامات التجارية',
        'read_more' => 'اقرأ المقال',
        'view_all' => 'كل المقالات',
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
