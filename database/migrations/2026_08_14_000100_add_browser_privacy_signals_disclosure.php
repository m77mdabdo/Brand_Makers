<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the "Browser privacy signals" section to the privacy policy on an
 * EXISTING install, without touching anything else on the page.
 *
 * WHY A MIGRATION AND NOT A RESEED. The disclosure ships in
 * PageContentSeeder for fresh installs, but that seeder is create-only by
 * design (see its class docblock): running it against a live site leaves
 * existing pages alone, so it will never deliver this section to a site
 * already running. And the alternative — updateOrCreate — would deliver it
 * by overwriting the whole page body, discarding any wording an admin had
 * refined in the dashboard. Neither is acceptable for legal copy.
 *
 * So this appends, surgically:
 *   - only the new section's HTML, appended to whatever body is there now;
 *   - only if that section is not already present, per locale, so running
 *     migrate twice — or running it against a fresh install that already
 *     seeded the section — is a no-op;
 *   - only for locales that actually have a body, so a page with no English
 *     translation does not gain an English-only one here.
 *
 * down() removes exactly what up() added, again per locale, so this is
 * genuinely reversible rather than reversible-in-name.
 *
 * IF AN ADMIN HAS ALREADY WRITTEN their own version of this disclosure in
 * different words, the marker below will not recognise it and the section
 * is appended anyway, leaving the policy saying the same thing twice. That
 * is the failure this errs towards deliberately: a duplicated paragraph is
 * visible and takes a minute to delete, whereas a missing disclosure is
 * invisible and is the one with legal consequences.
 *
 * WHAT IT DISCLOSES that the earlier copy did not: the DNT/GPC override
 * (which outranks consent), that query strings are never stored, and that
 * Meta and TikTok are not loaded at all before consent while Google runs
 * under Consent Mode. All three are behaviours this build introduced.
 */
return new class extends Migration
{
    /**
     * The heading is the idempotency marker: the one string in the section
     * that is stable, unique on the page, and would have to be removed
     * deliberately.
     *
     * @var array<string, string>
     */
    private const MARKERS = [
        'ar' => '<h2>إشارات الخصوصية في المتصفح</h2>',
        'en' => '<h2>Browser privacy signals</h2>',
    ];

    public function up(): void
    {
        $page = Page::where('slug', 'privacy-policy')->first();

        // No page means a fresh database that has not been seeded yet;
        // the seeder ships the section when it runs.
        if (! $page) {
            return;
        }

        $changed = false;

        foreach (self::MARKERS as $locale => $marker) {
            $body = $page->getTranslation('body', $locale, false);

            if (! is_string($body) || trim($body) === '') {
                continue;
            }

            if (str_contains($body, $marker)) {
                continue;
            }

            $page->setTranslation('body', $locale, $body.$this->section($locale));
            $changed = true;
        }

        if ($changed) {
            $page->save();
        }
    }

    public function down(): void
    {
        $page = Page::where('slug', 'privacy-policy')->first();

        if (! $page) {
            return;
        }

        $changed = false;

        foreach (array_keys(self::MARKERS) as $locale) {
            $body = $page->getTranslation('body', $locale, false);
            $section = $this->section($locale);

            if (! is_string($body) || ! str_contains($body, $section)) {
                continue;
            }

            // Removes the exact block up() added, and nothing adjacent.
            $page->setTranslation('body', $locale, str_replace($section, '', $body));
            $changed = true;
        }

        if ($changed) {
            $page->save();
        }
    }

    /**
     * The disclosure itself.
     *
     * Kept character-for-character identical to the copy in
     * PageContentSeeder::seedPrivacyPolicy(), so a fresh install and a
     * migrated one end up with the same policy — and so the marker check
     * above recognises the seeded version. PrivacyDisclosureTest asserts
     * that the two have not drifted.
     */
    private function section(string $locale): string
    {
        if ($locale === 'ar') {
            return '<h2>إشارات الخصوصية في المتصفح</h2>'
                .'<p>إذا كان متصفحك يرسل إشارة "عدم التتبع" (DNT) أو إشارة التحكم العام بالخصوصية (GPC)، فلا نسجّل جلستك ولا نُشغّل أي أداة تتبع إعلانية، حتى لو سبق أن وافقت على إشعار ملفات تعريف الارتباط. تُفحص هذه الإشارة قبل الموافقة، ولا تستطيع الموافقة إلغاءها.</p>'
                .'<p>ولا نخزّن كذلك أي شيء يأتي بعد علامة الاستفهام في الروابط التي تزورها؛ نحفظ المسار وحده. أما معطيات الحملات الإعلانية (utm) فتُحفظ في حقول مستقلة محددة سلفاً، لا كنص خام للرابط.</p>'
                .'<p>لا تُحمَّل بكسلات «ميتا» و«تيك توك» إطلاقاً قبل موافقتك، فلا يصل أي طلب إلى خوادمها. وتعمل أدوات «جوجل» بوضع الموافقة (Consent Mode) وكل إشاراته على «مرفوض» حتى توافق، فلا تحفظ ملفات تعريف ارتباط ولا ترسل معرّفات.</p>';
        }

        return '<h2>Browser privacy signals</h2>'
            .'<p>If your browser sends a Do Not Track (DNT) or Global Privacy Control (GPC) signal, we do not record your session and we do not run any advertising tracker — even if you previously accepted the cookie notice. The signal is checked before consent, and consent cannot override it.</p>'
            .'<p>We also do not store anything after the "?" in the URLs you visit; only the path is kept. Campaign parameters (utm) are stored in named fields of their own, never as raw URL text.</p>'
            .'<p>The Meta and TikTok pixels are not loaded at all before you agree — no request reaches their servers. Google\'s tags run in Consent Mode with every signal set to "denied" until you agree, so they store no cookies and send no identifiers.</p>';
    }
};
