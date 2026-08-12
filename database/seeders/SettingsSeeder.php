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
                'ar' => 'نبني العلامات التجارية التي تُذكر',
                'en' => 'We build brands that get remembered',
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
                'ar' => 'استوديو متخصص في بناء الهويات البصرية واستراتيجيات العلامات التجارية.',
                'en' => 'A studio specialising in visual identity and brand strategy.',
            ]],

            // --- Homepage SEO --------------------------------------------
            ['key' => 'home_meta_title', 'group' => 'home_seo', 'value' => [
                'ar' => 'صنّاع العلامات التجارية — تصميم الهوية البصرية واستراتيجية العلامة',
                'en' => 'Creative Mark — Visual Identity & Brand Strategy',
            ]],
            ['key' => 'home_meta_description', 'group' => 'home_seo', 'value' => [
                'ar' => 'نصمّم الهويات البصرية والشعارات ونبني استراتيجيات العلامات التجارية للشركات الناشئة والمؤسسات في السعودية والخليج.',
                'en' => 'Visual identity, logo design and brand strategy for startups and established companies across Saudi Arabia and the Gulf.',
            ]],

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
