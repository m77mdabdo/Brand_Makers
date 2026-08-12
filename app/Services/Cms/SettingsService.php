<?php

namespace App\Services\Cms;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The single source of truth for which settings exist, what type each is,
 * and how it is validated. The editor UI, the Form Request and the seeder
 * all read this one definition, so they cannot drift apart.
 *
 * WRITES ITERATE THE DEFINITION, NEVER THE REQUEST.
 *
 * This is the whole arbitrary-key defence, and it is deliberately two
 * independent layers:
 *
 *   1. UpdateSettingsRequest derives its rules from DEFINITION, so a key
 *      that is not listed has no rule and validated() drops it.
 *   2. update() below loops over DEFINITION and pulls values OUT of the
 *      payload by key. A key the payload contains but the definition does
 *      not is never read, let alone written.
 *
 * Either layer alone would be sufficient today. Both together mean a
 * crafted `settings[app_key]=...` cannot create a settings row even if
 * someone later loosens the validation — which is exactly the kind of
 * change that gets made without noticing what it was protecting.
 *
 * STORAGE NOTE. settings.value is a json column cast to 'array', but most
 * rows hold a JSON-encoded scalar. That round-trips fine (json_decode of
 * a quoted string gives the string back); bilingual settings use the
 * column properly, storing {"ar": "…", "en": "…"}.
 */
class SettingsService
{
    public const TYPE_TEXT = 'text';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_TRANSLATED = 'translated';

    public const TYPE_TRANSLATED_AREA = 'translated_area';

    public const TYPE_EMAIL = 'email';

    public const TYPE_PHONE = 'phone';

    public const TYPE_URL = 'url';

    public const TYPE_IMAGE = 'image';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_INTEGER = 'integer';

    /**
     * group => [key => type]. The order here is the order rendered in the
     * editor, so this array is also the page layout.
     *
     * @var array<string, array<string, string>>
     */
    public const DEFINITION = [
        'general' => [
            'site_name' => self::TYPE_TRANSLATED,
            'site_tagline' => self::TYPE_TRANSLATED,
            'site_logo' => self::TYPE_IMAGE,
        ],
        'contact' => [
            'contact_phone' => self::TYPE_PHONE,
            'contact_email' => self::TYPE_EMAIL,
            'contact_address' => self::TYPE_TRANSLATED,
        ],
        'whatsapp' => [
            'contact_whatsapp' => self::TYPE_PHONE,
        ],
        'social' => [
            'social_instagram' => self::TYPE_URL,
            'social_behance' => self::TYPE_URL,
            'social_linkedin' => self::TYPE_URL,
            'social_x' => self::TYPE_URL,
            'social_tiktok' => self::TYPE_URL,
            'social_facebook' => self::TYPE_URL,
            'social_youtube' => self::TYPE_URL,
        ],
        'seo_defaults' => [
            'default_meta_title' => self::TYPE_TRANSLATED,
            'default_meta_description' => self::TYPE_TRANSLATED_AREA,
        ],
        /*
         * The homepage is the only public page with no backing record, so
         * it has nothing to hang a SeoMeta row off. Without these it falls
         * through to the site-wide defaults — which is how the most-shared
         * page on a site ends up with a generic <title>.
         *
         * TRANSLATED rather than plain text: / and /en are separate
         * documents to a crawler and deserve their own copy.
         */
        'home_seo' => [
            'home_meta_title' => self::TYPE_TRANSLATED,
            'home_meta_description' => self::TYPE_TRANSLATED_AREA,
        ],
        /*
         * The hero's three figures.
         *
         * Seeded EMPTY and hidden when empty — the hero renders no stats
         * block at all unless an admin has entered real ones. The design
         * reference carried "+500 companies / 48h / 98% satisfaction", and
         * shipping those as defaults would publish claims nobody has
         * verified on the most visible part of the site.
         *
         * TRANSLATED because the values carry units and numerals that
         * differ by locale — "٤٨ ساعة" and "48 hours" are not the same
         * string with a different label around them.
         */
        'home_stats' => [
            'home_stat_companies' => self::TYPE_TRANSLATED,
            'home_stat_turnaround' => self::TYPE_TRANSLATED,
            'home_stat_satisfaction' => self::TYPE_TRANSLATED,
        ],
    ];

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Every editable key, flattened.
     *
     * @return array<string, string> key => type
     */
    public static function keys(): array
    {
        return array_merge(...array_values(self::DEFINITION));
    }

    public static function typeOf(string $key): ?string
    {
        return self::keys()[$key] ?? null;
    }

    /**
     * Persists the submitted values.
     *
     * @param  array<string, mixed>  $values  the validated `settings` payload
     */
    public function update(array $values, ?UploadedFile $logo = null): void
    {
        DB::transaction(function () use ($values, $logo) {
            foreach (self::DEFINITION as $group => $keys) {
                foreach ($keys as $key => $type) {
                    // Images come from an upload, not the text payload, and
                    // an absent file must leave the current one alone
                    // rather than clearing it.
                    if ($type === self::TYPE_IMAGE) {
                        if ($logo && $key === 'site_logo') {
                            $this->writeImage($key, $group, $logo);
                        }

                        continue;
                    }

                    // An unchecked checkbox is absent from the payload
                    // entirely, so a boolean cannot use the guard below:
                    // "not submitted" IS false here, whereas for every
                    // other type it means "leave alone".
                    if ($type === self::TYPE_BOOLEAN) {
                        $this->write($key, $group, (bool) ($values[$key] ?? false));

                        continue;
                    }

                    // array_key_exists, not isset: a field the admin
                    // deliberately cleared arrives as null and must be
                    // stored as null.
                    if (! array_key_exists($key, $values)) {
                        continue;
                    }

                    $this->write($key, $group, $this->normalise($type, $values[$key]));
                }
            }
        });

        // The repository holds the table in memory for the rest of the
        // request, so without this the redirect would re-render the values
        // as they were before the save.
        $this->settings->flush();
    }

    private function writeImage(string $key, string $group, UploadedFile $file): void
    {
        $previous = $this->settings->get($key);

        $path = $file->store('settings', 'public');

        // Remove the file being replaced. Guarded on it being a string
        // under our own directory so a hand-edited row can never make this
        // delete something else on the disk.
        if (is_string($previous) && str_starts_with($previous, 'settings/')) {
            Storage::disk('public')->delete($previous);
        }

        $this->write($key, $group, $path);
    }

    private function write(string $key, string $group, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }

    /**
     * Blank becomes null, so "never set" and "cleared" are one state.
     *
     * Every consumer — the footer, the contact block, the hero stats —
     * tests for a truthy value before rendering, so a stored "" would
     * otherwise print an empty row or an empty stat.
     */
    private function normalise(string $type, mixed $value): mixed
    {
        if ($type === self::TYPE_INTEGER) {
            return is_numeric($value) ? (int) $value : null;
        }

        if (in_array($type, [self::TYPE_TRANSLATED, self::TYPE_TRANSLATED_AREA], true)) {
            $value = array_filter(
                array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $value),
                fn ($v) => is_string($v) && $v !== ''
            );

            return $value === [] ? null : $value;
        }

        $value = is_string($value) ? trim($value) : $value;

        return $value === '' ? null : $value;
    }
}
