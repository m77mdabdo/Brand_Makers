<?php

/**
 * English counterpart to lang/ar/site.php. Same keys, professional
 * English — not a machine translation of the Arabic.
 */
return [

    'brand' => [
        'name' => 'Creative Mark',
        'name_latin' => 'Creative Mark',
        'tagline' => 'Your partner for company formation and investment in Saudi Arabia',
    ],

    'nav' => [
        'breadcrumb' => 'Breadcrumb',
        'home' => 'Home',
        'skip_to_content' => 'Skip to main content',
        'open_menu' => 'Open menu',
        'close_menu' => 'Close menu',
        'menu_dialog_label' => 'Navigation menu',
        'back_to_top' => 'Back to top',
        'switch_to_english' => 'English',
        'switch_to_arabic' => 'العربية',
        'switch_language_label' => 'Change language',
        'book_consultation' => 'Book a consultation',
    ],

    'hero' => [
        'eyebrow' => 'Company formation & investment · Saudi Arabia',
        'title_before' => 'Your trusted partner for forming',
        'title_highlight' => 'your company',
        'title_after' => 'and investing in Saudi Arabia',
        'lead' => 'From an idea to an active Commercial Registration — we handle the formation, the licences, the acquisition and every government procedure for you, with clear steps, published prices, and a specialist Saudi team beside you at each one.',
        'cta_primary' => 'Start now',
        'cta_secondary' => 'Estimate your cost',
        'scroll_cue' => 'Scroll down',
        // Labels for the hero figures. The VALUES are editable
        // settings (seeded empty); these labels are chrome.
        'stats' => [
            'companies' => 'companies formed',
            'turnaround' => 'average turnaround',
            'satisfaction' => 'client satisfaction',
        ],
        'background_label' => 'Decorative background: a city skyline at dusk',
    ],

    // The cookie banner. Both buttons are real answers: declining stores
    // "denied" so the visitor is not asked again, and ignoring the banner
    // is treated exactly like declining server-side.
    'consent' => [
        'aria_label' => 'Cookie consent',
        'message' => 'We record how visitors move through this site, and use advertising tools, only if you agree. Nothing is recorded before you do, and we never store your full IP address.',
        'policy_link' => 'Read our privacy policy',
        'accept' => 'Accept',
        'decline' => 'Decline',
    ],

    'marquee' => [
        'label' => 'What we do',
    ],

    // The four value props under the hero. Fixed copy rather than
    // database content, on the same rule as the hero itself.
    'values' => [
        'eyebrow' => 'Why us',
        'title' => 'Formation whose steps and cost you know from the start',
        'items' => [
            ['title' => 'End-to-end formation', 'body' => 'Every procedure and licence under one roof.'],
            ['title' => '100% foreign ownership', 'body' => 'In the activities where it is permitted.'],
            ['title' => 'Fast and transparent', 'body' => 'Clear steps, live progress, published prices.'],
            ['title' => 'Post-formation support', 'body' => 'Accounting, tax, zakat, social insurance and compliance.'],
        ],
    ],

    // The homepage activities section. Titles and descriptions come from
    // the Activities page's sections in the database; this is the frame.
    'activities' => [
        'eyebrow' => 'Activities',
        'title' => 'We form and licence across every sector',
        'lead' => 'We form and licence companies across every economic sector, and guide you to the activity and entity that fit best.',
        'view_all' => 'Explore the activities',
    ],

    'why_saudi' => [
        'eyebrow' => 'The market',
        'title' => 'Why invest in Saudi Arabia',
        'view_all' => 'Read more',
    ],

    'services' => [
        'label' => 'Services',
        'on_request' => 'Priced on request',
        'currency' => 'SAR',
        'read_more' => 'Read more',
        'requirements' => 'What we need from you',
        'process' => 'How it works',
        'enquire' => 'Enquire about this service',
        'other' => 'Other services',
        'eyebrow' => 'What we do',
        'title' => 'Everything it takes to form your entity and run it',
        'lead' => 'From choosing the activity and the licences through to accounting and compliance once you are operating — under one roof.',
        'view_all' => 'All services',
        'empty' => 'No services have been added yet.',
    ],

    'home_estimator' => [
        'eyebrow' => 'Interactive calculator',
        'title' => 'Estimate your formation cost in seconds',
        'lead' => 'Pick your package and any add-ons you need, and get an instant indicative figure — no phone call required.',
        'cta' => 'Open the estimator',
        'panel_title' => 'Indicative estimate',
        'total_label' => 'Estimated cost',
        'currency' => 'SAR',
        'disclaimer' => 'Indicative only and not binding. A formal proposal follows your consultation.',
        'unpriced' => 'Pricing in preparation',
        'unpriced_hint' => 'Get in touch for a detailed quote for forming your entity.',
    ],

    'packages' => [
        'enquiry_heading' => 'Request the :package package',
        'enquiry_lead' => 'Leave your details and we will be in touch about this package.',
        'back' => 'Back to packages',
        'label' => 'Packages',
        'compare' => 'Compare packages',
        'feature' => 'Feature',
        'included' => 'Included',
        'not_included' => 'Not included',
        'eyebrow' => 'Our packages',
        'title' => 'Pick the package that fits your case',
        'lead' => 'Published prices and defined inclusions. Compare and choose, or ask for a scope tailored to your activity.',
        'popular' => 'Most popular',
        'price_on_request' => 'On request',
        'currency' => 'SAR',
        'choose' => 'Request this package',
        'view_all' => 'All packages',
        'empty' => 'No packages have been added yet.',
    ],

    'home_tracker' => [
        'eyebrow' => 'Client portal',
        'title' => 'Follow your application, step by step',
        'lead' => 'Every order gets its own private tracking link showing the current stage, progress and latest update.',
        'cta' => 'Track your order',
        'steps' => [
            ['title' => 'Place the order', 'body' => 'Choose a package and get started, and we establish which documents are needed.'],
            ['title' => 'Procedures and licences', 'body' => 'We submit the applications and follow them with the competent authorities.'],
            ['title' => 'Handover', 'body' => 'You receive the registration and the licences, with your entity ready to operate.'],
        ],
        'sample_reference' => 'Order #CM-XXXX',
        'sample_status' => 'In progress',
        'sample_note' => 'An illustration of the tracking screen.',
        'progress_label' => 'Progress',
    ],

    'home_booking' => [
        'title' => 'Ready to start your entity?',
        'lead' => 'Book a consultation and we will settle the right activity and entity, the steps and the cost with you.',
        'cta' => 'Book a consultation',
    ],

    'testimonials' => [
        'eyebrow' => 'Client feedback',
        'title' => 'What clients said about working with us',
    ],

    'faq' => [
        'label' => 'FAQ',
        'lead' => 'Answers to what we are asked most. If your question is not here, get in touch.',
        'empty' => 'No questions yet.',
        'eyebrow' => 'FAQ',
        'title' => 'Questions we are asked often',
        'view_all' => 'All questions',
    ],

    'articles' => [
        'label' => 'Blog',
        'empty' => 'No articles published yet.',
        'related' => 'Related articles',
        'eyebrow' => 'From the blog',
        'title' => 'Guides and articles on formation and investment',
        'read_more' => 'Read the article',
        'view_all' => 'All articles',
    ],

    'errors' => [
        'back_home' => 'Back to the homepage',
        'not_found' => ['title' => 'Page not found', 'message' => 'The link you opened no longer exists, or it may have been mistyped.'],
        'server_error' => ['title' => 'Something went wrong', 'message' => 'The problem is on our side and has been logged. Please try again shortly.'],
        'forbidden' => ['title' => 'You do not have access', 'message' => 'This area is for staff only.'],
        'expired' => ['title' => 'Your session expired', 'message' => 'Refresh the page and try again.'],
        'maintenance' => ['title' => 'Down for maintenance', 'message' => 'We are making a quick update and will be back in a few minutes.'],
    ],

    'countries' => [
        'label' => 'Markets',
        'eyebrow' => 'Where we work',
        'title' => 'The markets we serve',
        'lead' => 'We serve investors from Saudi Arabia, the Gulf and beyond the region.',
        'empty' => 'No markets have been added yet.',
    ],

    'contact' => [
        'label' => 'Contact',
        'eyebrow' => 'Get in touch',
        'title' => 'Contact us',
        'lead' => 'Tell us about your project and we will come back with a clear plan.',
        'whatsapp' => 'WhatsApp',
        'phone' => 'Phone',
        'email' => 'Email',
        'address' => 'Address',
        'social' => 'Follow us',
        'no_details' => 'Contact details are being set up.',
        'form_coming' => 'The enquiry form is on its way. Until then, please use any of the contact details above.',
    ],

    'comments' => [
        'form_heading' => 'Add a comment',
        'moderation_note' => 'Comments are reviewed before they appear.',
        'heading' => '{0} No comments|{1} One comment|[2,*] :count comments',
    ],

    'forms' => [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone number',
        'message' => 'Your message',
        'comment' => 'Your comment',
        'consent' => 'Consent',
        'required' => 'required',
        'optional' => 'optional',
        'submit' => 'Send',
        'honeypot_label' => 'Leave this field empty',
        'errors_heading' => 'Please check the following:',
        'consent_label' => 'I agree to my details being stored so you can reply to my enquiry.',
        'consent_note' => 'We never share your details with anyone else. See our :policy.',
        'heading' => 'Send us a message',
        'lead' => 'Fill in the form and we will come back to you shortly.',
    ],

    // The cost estimator. The GROUP headings are keyed by the stable
    // `group` slug on estimator_items, with the raw slug as the fallback,
    // so a group an admin adds still renders rather than showing a missing
    // translation key.
    'estimator' => [
        'label' => 'Cost Estimator',
        'eyebrow' => 'Plan your budget',
        'title' => 'Estimate your formation cost',
        'lead' => 'Choose your formation type, then the services you need, and see an indicative figure straight away.',
        'step_package' => 'Formation type',
        'step_package_help' => 'Optional. Start from one of the packages, or build your estimate from the services alone.',
        'none_selected' => 'Nothing selected',
        'selected_count' => ':count selected',
        'no_package' => 'No base package',
        'on_request' => 'Priced on request',
        'currency' => 'SAR',
        'summary' => 'Your estimate',
        'empty_selection' => 'Tick what you need and the figure will appear here.',
        'estimated_total' => 'Estimated total',
        'estimated_from' => 'Estimated from',
        'partial_note' => 'Part of your selection is priced on request, so the real figure will be higher than the one shown.',
        'reset' => 'Clear selection',
        'disclaimer' => 'This is an indicative estimate and excludes government fees; it is not a binding offer. The final cost depends on your activity, the investor\'s nationality and the licences required, and is confirmed in writing after a consultation.',
        'request_cta' => 'Get an exact quote',
        'request_title' => 'Send me this estimate',
        'request_lead' => 'Leave your details and we will come back with an exact quote for the selection above.',
        'full_name' => 'Full name',
        'email' => 'Email address',
        'phone_optional' => 'Phone number (optional)',
        'message' => 'Anything else we should know?',
        'consent' => 'Consent',
        'submit' => 'Send me this estimate',
        'unpriced' => 'Pricing in preparation',
        'unpriced_hint' => 'The estimator is not open yet — our rates are being finalised. Get in touch and we will price your project directly.',
        'unpriced_cta' => 'Contact us',
        'groups' => [
            'licensing' => 'Licensing',
            'banking' => 'Banking and address',
            'compliance' => 'Accounting and compliance',
            'advisory' => 'Advisory',
        ],
    ],

    // Appointment booking. Times shown to a visitor are always the
    // office's wall clock — see config/booking.php.
    'booking' => [
        'label' => 'Book a consultation',
        'eyebrow' => 'Talk to us',
        'title' => 'Book a consultation',
        'lead' => 'Pick a time that suits you. The session is a conversation about your plans — the activity, the entity and what forming it involves.',
        'timezone_label' => 'Riyadh time',
        'timezone_note' => 'All times are shown in :timezone.',
        'step_topic' => '1 · What would you like to discuss?',
        'step_topic_help' => 'Optional — it just helps us prepare.',
        'topic' => 'Topic',
        'topic_unsure' => 'Not sure yet',
        'step_date' => '2 · Pick a day',
        'step_time' => '3 · Pick a time',
        'pick_a_day_first' => 'Pick a day above and the free times will appear here.',
        'step_details' => '4 · Your details',
        'selected' => 'Your appointment',
        'full_name' => 'Full name',
        'email' => 'Email address',
        'phone_optional' => 'Phone number (optional)',
        'notes' => 'Anything you would like us to know beforehand?',
        'date' => 'Date',
        'time' => 'Time',
        'consent' => 'Consent',
        'submit' => 'Request this appointment',
        'confirmed_title' => 'Your appointment is requested',
        'confirmed_lead' => 'Keep this reference — quote it if you need to change anything.',
        'reference' => 'Booking reference',
        'appointment' => 'Appointment',
        'confirmed_next' => 'We will be in touch to confirm the session. All times are :timezone.',
        'slot_gone' => 'That time is no longer being offered. Please pick another.',
        'slot_taken' => 'Someone booked that slot moments ago. Please pick another time — your details have been kept.',
        'not_open' => 'Booking is not open yet',
        'not_open_hint' => 'Our consultation calendar is not published yet. Get in touch and we will arrange a time directly.',
        'none_available' => 'No times are free at the moment',
        'none_available_hint' => 'Every slot in the current window is taken. Get in touch and we will find a time for you.',
        'contact_cta' => 'Contact us',
        'noscript_title' => 'Available times',
        'noscript_hint' => 'The picker needs JavaScript. These are the times currently free — contact us to take one.',
    ],

    // The client portal. Nothing here identifies an order in a URL — see
    // OrderTrackerController.
    'tracker' => [
        'label' => 'Track your order',
        'eyebrow' => 'Client portal',
        'title' => 'Track your order',
        'lead' => 'Enter your order reference and the tracking code we sent you to see exactly where your application has got to.',
        'form_heading' => 'Open your order',
        'form_hint' => 'Both are on the confirmation we sent you. The tracking code is long — paste it rather than typing it.',
        'reference' => 'Order reference',
        'code' => 'Tracking code',
        'submit' => 'Open my order',
        'not_found' => 'We could not open an order with those details. Check the reference and the tracking code and try again.',
        'lost_code' => 'Lost your tracking code? We cannot recover it — it is never stored in a readable form — but we can issue a new one.',
        'contact_us' => 'Get in touch',
        'package' => 'Package',
        'progress' => 'Progress',
        'stages_heading' => 'Where your project is',
        'updates_heading' => 'Updates from the team',
        'latest_note' => 'Latest note',
        'cancelled_note' => 'This order was cancelled. The stages below show how far it had got.',
        'check_another' => 'Check another order',
        'statuses' => [
            'pending' => 'Received',
            'in_progress' => 'In production',
            'in_review' => 'In review',
            'completed' => 'Delivered',
            'cancelled' => 'Cancelled',
        ],
        'stages' => [
            'pending' => 'Received',
            'in_progress' => 'In production',
            'in_review' => 'In review with you',
            'completed' => 'Delivered',
        ],
        'stage_states' => [
            'done' => 'Done',
            'current' => 'In progress now',
            'upcoming' => 'Still to come',
        ],
    ],

    'flash' => [
        'booking_submitted' => 'Thank you. Your appointment request has been received and we will confirm it shortly.',
        'estimate_submitted' => 'Thank you. We have your estimate and will come back with an exact quote shortly.',
        'contact_submitted' => 'Thank you. We have your message and will come back to you shortly.',
        'comment_submitted' => 'Thank you. Your comment will appear once it has been reviewed.',
        'package_enquiry_submitted' => 'Thank you. We have your request and will be in touch about the package.',
    ],

    'footer' => [
        'about' => 'A Saudi advisory firm specialising in company formation and investment enablement in the Kingdom of Saudi Arabia.',
        'links_about' => 'About',
        'links_process' => 'How we work',
        'links_heading' => 'Links',
        'contact_heading' => 'Contact',
        'legal_heading' => 'Legal',
        'rights' => 'All rights reserved.',
        'privacy_link' => 'Privacy policy',
        'terms_link' => 'Terms & conditions',
    ],
];
