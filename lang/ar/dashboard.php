<?php

/**
 * Dashboard (staff-facing) UI strings.
 *
 * Separate from the site.php files on purpose: that file is the PUBLIC
 * site's chrome and is part of what visitors read, whereas this is the
 * back office. Keeping them apart means a copy change to one cannot
 * accidentally alter the other.
 */
return [

    'title' => 'لوحة التحكم',

    'nav' => [
        'overview' => 'نظرة عامة',
        'content' => 'المحتوى',
        'settings_group' => 'الإعدادات',
        'dashboard' => 'الرئيسية',
        'settings' => 'إعدادات الموقع',
        'view_site' => 'عرض الموقع',
    ],

    'topbar' => [
        'open_menu' => 'فتح القائمة',
        'close_menu' => 'إغلاق القائمة',
        'menu_dialog_label' => 'قائمة لوحة التحكم',
        'skip_to_content' => 'تخطَّ إلى المحتوى الرئيسي',
        'switch_to_english' => 'English',
        'switch_to_arabic' => 'العربية',
        'switch_language_label' => 'تغيير لغة لوحة التحكم',
        'account_menu' => 'قائمة الحساب',
        'profile' => 'الملف الشخصي',
    ],

    'auth' => [
        'login_title' => 'تسجيل الدخول',
        'login_intro' => 'سجّل دخولك للوصول إلى لوحة التحكم.',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'remember_me' => 'تذكّرني',
        'forgot_password' => 'نسيت كلمة المرور؟',
        'login' => 'دخول',
        'logout' => 'تسجيل الخروج',
        'back_to_site' => 'العودة إلى الموقع',
    ],

    'home' => [
        'heading' => 'نظرة عامة',
        'intro' => 'ملخّص سريع لمحتوى الموقع والطلبات الواردة.',
        'unhandled_leads' => 'طلبات لم تُعالَج بعد',
        'stats' => [
            'services' => 'الخدمات',
            'packages' => 'الباقات',
            'articles' => 'المقالات',
            'leads' => 'الطلبات',
            'bookings' => 'الحجوزات',
            'orders' => 'طلبات العملاء',
        ],
    ],

    'common' => [
        'save' => 'حفظ التغييرات',
        'arabic' => 'العربية',
        'english' => 'English',
        'optional' => 'اختياري',
        'current' => 'الحالي',
        'remove' => 'إزالة',
        'none_set' => 'غير محدّد',
    ],

    'flash' => [
        'settings_updated' => 'تم حفظ الإعدادات.',
    ],

    'settings' => [
        'heading' => 'إعدادات الموقع',
        'intro' => 'كل ما يظهر في ترويسة الموقع وتذييله وبطاقات المشاركة. الحقول الفارغة لا تُعرض على الموقع إطلاقاً.',
        'groups' => [
            'general' => 'عام',
            'contact' => 'بيانات التواصل',
            'whatsapp' => 'واتساب',
            'social' => 'حسابات التواصل الاجتماعي',
            'seo_defaults' => 'إعدادات SEO الافتراضية',
            'home_seo' => 'SEO الصفحة الرئيسية',
            'home_stats' => 'أرقام الصفحة الرئيسية',
        ],
        'group_help' => [
            'contact' => 'تُعرض في التذييل وصفحة التواصل. اتركها فارغة إن لم تكن جاهزة — لن يظهر مكانها شيء.',
            'social' => 'الروابط الفارغة لا تظهر في التذييل.',
            'seo_defaults' => 'تُستخدم فقط عند عدم وجود عنوان أو وصف خاص بالصفحة.',
            'home_seo' => 'الصفحة الرئيسية ليس لها سجل محتوى تستمد منه بياناتها، لذلك تُكتب هنا.',
            'home_stats' => 'أرقام تظهر في أعلى الصفحة الرئيسية. تُترك فارغة افتراضياً — لا تظهر أي أرقام على الموقع حتى تكتبها بنفسك.',
        ],
        'fields' => [
            'site_name' => 'اسم الموقع',
            'site_tagline' => 'الشعار النصي',
            'site_logo' => 'شعار الموقع',
            'contact_phone' => 'رقم الهاتف',
            'contact_email' => 'البريد الإلكتروني',
            'contact_address' => 'العنوان',
            'contact_whatsapp' => 'رقم واتساب',
            'social_instagram' => 'إنستغرام',
            'social_behance' => 'بيهانس',
            'social_linkedin' => 'لينكد إن',
            'social_x' => 'X (تويتر)',
            'social_tiktok' => 'تيك توك',
            'social_facebook' => 'فيسبوك',
            'social_youtube' => 'يوتيوب',
            'default_meta_title' => 'العنوان الافتراضي',
            'default_meta_description' => 'الوصف الافتراضي',
            'home_meta_title' => 'عنوان الصفحة الرئيسية',
            'home_meta_description' => 'وصف الصفحة الرئيسية',
            'home_stat_companies' => 'عدد المشاريع',
            'home_stat_turnaround' => 'متوسط مدة الإنجاز',
            'home_stat_satisfaction' => 'نسبة رضا العملاء',
        ],
        'logo_help' => 'يحل محل الشعار الافتراضي في التذييل. JPG أو PNG أو WebP، بحد أقصى 2 ميجابايت.',
    ],
];
