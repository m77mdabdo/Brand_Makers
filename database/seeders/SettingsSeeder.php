<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds every key the Settings editor knows about, so a fresh install has
 * a complete, editable set rather than a half-populated form.
 *
 * Social and contact values are seeded EMPTY on purpose. A placeholder
 * phone number or a "https://instagram.com/yourpage" link is worse than a
 * blank: it renders as a visibly broken control on the public site, and it
 * fails the editor's own URL validation the next time anyone saves. Empty
 * means "not configured", and the front end hides what is not configured.
 *
 * The brand strings and the two homepage SEO values ARE seeded, because
 * they are real and the homepage has no backing record to carry a SeoMeta
 * row — without them the most-shared page on the site would fall through
 * to a generic default title.
 *
 * Idempotent, and existing values are NEVER overwritten: re-seeding must
 * not wipe contact details an admin has entered. Only the `group` is
 * corrected on an existing row, since that is structure rather than
 * content.
 */
class SettingsSeeder extends Seeder
{
    /**
     * The single source of truth for which settings exist. Tests count
     * this rather than a hard-coded number, so adding a setting stays a
     * one-file change.
     *
     * @return array<int, array{key: string, group: string, value: mixed}>
     */
    public static function definitions(): array
    {
        return [
            // --- General -------------------------------------------------
            ['key' => 'site_name', 'group' => 'general', 'value' => [
                'ar' => 'صنّاع العلامات التجارية',
                'en' => 'Creative Mark',
            ]],
            ['key' => 'site_tagline', 'group' => 'general', 'value' => [
                'ar' => 'شريكك في تأسيس الشركات والاستثمار في السعودية',
                'en' => 'Your partner for company formation and investment in Saudi Arabia',
            ]],
            ['key' => 'site_logo', 'group' => 'general', 'value' => null],

            // --- Contact — blank until configured ------------------------
            ['key' => 'contact_phone', 'group' => 'contact', 'value' => null],
            ['key' => 'contact_email', 'group' => 'contact', 'value' => null],
            ['key' => 'contact_address', 'group' => 'contact', 'value' => null],
            ['key' => 'contact_whatsapp', 'group' => 'whatsapp', 'value' => null],

            // --- Social — blank until configured -------------------------
            ['key' => 'social_instagram', 'group' => 'social', 'value' => null],
            ['key' => 'social_behance', 'group' => 'social', 'value' => null],
            ['key' => 'social_linkedin', 'group' => 'social', 'value' => null],
            ['key' => 'social_x', 'group' => 'social', 'value' => null],
            ['key' => 'social_tiktok', 'group' => 'social', 'value' => null],
            ['key' => 'social_facebook', 'group' => 'social', 'value' => null],
            ['key' => 'social_youtube', 'group' => 'social', 'value' => null],

            // --- SEO defaults --------------------------------------------
            ['key' => 'default_meta_title', 'group' => 'seo_defaults', 'value' => [
                'ar' => 'صنّاع العلامات التجارية',
                'en' => 'Creative Mark',
            ]],
            ['key' => 'default_meta_description', 'group' => 'seo_defaults', 'value' => [
                'ar' => 'تأسيس الشركات وتمكين الاستثمار في المملكة العربية السعودية.',
                'en' => 'Company formation and investment enablement in the Kingdom of Saudi Arabia.',
            ]],

            // --- Homepage SEO --------------------------------------------
            ['key' => 'home_meta_title', 'group' => 'home_seo', 'value' => [
                'ar' => 'صنّاع العلامات التجارية — تأسيس الشركات والاستثمار في السعودية',
                'en' => 'Creative Mark — Company Formation & Investment in Saudi Arabia',
            ]],
            ['key' => 'home_meta_description', 'group' => 'home_seo', 'value' => [
                'ar' => 'نؤسّس شركتك في السعودية ونستخرج تراخيصها: تأسيس واستحواذ ورخص استثمار وسجل تجاري وخدمات ما بعد التأسيس، بأسعار معلنة.',
                'en' => 'We form your company in Saudi Arabia and obtain its licences: formation, acquisition, investment licences, Commercial Registration and post-formation services, at published prices.',
            ]],

            // --- Homepage stats ------------------------------------------
            // Seeded EMPTY and hidden when empty. The design reference
            // carried "+500 companies / 48h / 98% satisfaction"; shipping
            // those as defaults would publish unverified claims on the
            // most visible part of the site. An admin enters real figures
            // in Settings and the hero starts rendering them.
            ['key' => 'home_stat_companies', 'group' => 'home_stats', 'value' => null],
            ['key' => 'home_stat_turnaround', 'group' => 'home_stats', 'value' => null],
            ['key' => 'home_stat_satisfaction', 'group' => 'home_stats', 'value' => null],

            // --- Visitor sessions ----------------------------------------
            // Capture defaults ON but records nothing until a visitor
            // accepts the cookie notice, so a fresh install is not quietly
            // collecting anything. 90 days matches the retention window
            // named in the privacy policy.
            ['key' => 'visitor_sessions_enabled', 'group' => 'visitor_sessions', 'value' => true],
            ['key' => 'visitor_sessions_retention_days', 'group' => 'visitor_sessions', 'value' => 90],

            // --- Bookings -------------------------------------------------
            // How far ahead the public booking calendar opens, and the
            // minimum notice before the next bookable slot.
            ['key' => 'booking_window_days', 'group' => 'booking', 'value' => 30],
            ['key' => 'booking_minimum_notice_hours', 'group' => 'booking', 'value' => 24],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $setting) {
            $existing = Setting::where('key', $setting['key'])->first();

            if ($existing) {
                // Only correct the group; leave whatever value is stored.
                $existing->update(['group' => $setting['group']]);

                continue;
            }

            Setting::create($setting);
        }
    }
}
