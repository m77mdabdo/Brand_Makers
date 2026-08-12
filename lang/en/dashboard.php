<?php

/**
 * English counterpart to lang/ar/dashboard.php.
 */
return [

    'title' => 'Dashboard',

    'nav' => [
        'overview' => 'Overview',
        'content' => 'Content',
        'settings_group' => 'Settings',
        'dashboard' => 'Home',
        'settings' => 'Site settings',
        'view_site' => 'View site',
    ],

    'topbar' => [
        'open_menu' => 'Open menu',
        'close_menu' => 'Close menu',
        'menu_dialog_label' => 'Dashboard menu',
        'skip_to_content' => 'Skip to main content',
        'switch_to_english' => 'English',
        'switch_to_arabic' => 'العربية',
        'switch_language_label' => 'Change dashboard language',
        'account_menu' => 'Account menu',
        'profile' => 'Profile',
    ],

    'auth' => [
        'login_title' => 'Sign in',
        'login_intro' => 'Sign in to reach the dashboard.',
        'email' => 'Email',
        'password' => 'Password',
        'remember_me' => 'Remember me',
        'forgot_password' => 'Forgot your password?',
        'login' => 'Sign in',
        'logout' => 'Sign out',
        'back_to_site' => 'Back to the site',
    ],

    'home' => [
        'heading' => 'Overview',
        'intro' => 'A quick summary of the site content and incoming enquiries.',
        'unhandled_leads' => 'enquiries not yet handled',
        'stats' => [
            'services' => 'Services',
            'packages' => 'Packages',
            'articles' => 'Articles',
            'leads' => 'Enquiries',
            'bookings' => 'Bookings',
            'orders' => 'Orders',
        ],
    ],

    'common' => [
        'save' => 'Save changes',
        'arabic' => 'العربية',
        'english' => 'English',
        'optional' => 'optional',
        'current' => 'Current',
        'remove' => 'Remove',
        'none_set' => 'Not set',
    ],

    'flash' => [
        'settings_updated' => 'Settings saved.',
    ],

    'settings' => [
        'heading' => 'Site settings',
        'intro' => 'Everything that appears in the site header, footer and share cards. Empty fields are not rendered on the site at all.',
        'groups' => [
            'general' => 'General',
            'contact' => 'Contact details',
            'whatsapp' => 'WhatsApp',
            'social' => 'Social accounts',
            'seo_defaults' => 'SEO defaults',
            'home_seo' => 'Homepage SEO',
            'home_stats' => 'Homepage stats',
        ],
        'group_help' => [
            'contact' => 'Shown in the footer and on the contact page. Leave blank if not ready — nothing is rendered in its place.',
            'social' => 'Empty links are hidden in the footer.',
            'seo_defaults' => 'Used only when a page has no title or description of its own.',
            'home_seo' => 'The homepage has no content record to draw from, so its metadata is written here.',
            'home_stats' => 'Figures shown at the top of the homepage. Blank by default — no numbers appear on the site until you enter real ones.',
        ],
        'fields' => [
            'site_name' => 'Site name',
            'site_tagline' => 'Tagline',
            'site_logo' => 'Site logo',
            'contact_phone' => 'Phone number',
            'contact_email' => 'Email address',
            'contact_address' => 'Address',
            'contact_whatsapp' => 'WhatsApp number',
            'social_instagram' => 'Instagram',
            'social_behance' => 'Behance',
            'social_linkedin' => 'LinkedIn',
            'social_x' => 'X (Twitter)',
            'social_tiktok' => 'TikTok',
            'social_facebook' => 'Facebook',
            'social_youtube' => 'YouTube',
            'default_meta_title' => 'Default title',
            'default_meta_description' => 'Default description',
            'home_meta_title' => 'Homepage title',
            'home_meta_description' => 'Homepage description',
            'home_stat_companies' => 'Projects delivered',
            'home_stat_turnaround' => 'Average turnaround',
            'home_stat_satisfaction' => 'Client satisfaction',
        ],
        'logo_help' => 'Replaces the default logo in the footer. JPG, PNG or WebP, up to 2 MB.',
    ],
];
