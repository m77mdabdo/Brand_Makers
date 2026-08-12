<?php

namespace App\Services\Cms;

use Mews\Purifier\Facades\Purifier;

/**
 * Sanitising happens on WRITE, never on render.
 *
 * The database therefore never holds markup nobody vetted, which means a
 * later change to how a field is displayed — a new export, an API, an
 * email template — cannot resurrect a stored payload. Escaping at render
 * time protects only the renderers that remember to do it.
 */
class HtmlSanitizerService
{
    /**
     * Rich body HTML, against the narrow 'article' allow-list in
     * config/purifier.php.
     */
    public function sanitizeRichText(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = trim(Purifier::clean($html, 'article'));

        return $clean === '' ? null : $clean;
    }

    /**
     * Strips markup entirely, via an empty allow-list rather than PHP's
     * strip_tags().
     *
     * strip_tags() removes tags but leaves element BODIES, so
     * "<script>alert(1)</script>" survives as the visible text "alert(1)".
     * HTMLPurifier removes both.
     */
    public function stripAllTags(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $clean = trim(Purifier::clean($value, 'plain_text'));

        return $clean === '' ? null : $clean;
    }

    /**
     * A per-locale map, sanitised value by value, with blank locales
     * dropped.
     *
     * Dropping blanks is what keeps a translatable column storing
     * {"ar": "..."} rather than {"ar": "...", "en": ""} — an empty string
     * is a present value to spatie/laravel-translatable, so it would beat
     * the Arabic fallback and render as a gap on the English site.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, string>|null
     */
    public function sanitizeTranslations(?array $values, bool $richText = false): ?array
    {
        $clean = [];

        foreach ($values ?? [] as $locale => $value) {
            if (! is_string($value)) {
                continue;
            }

            $sanitized = $richText ? $this->sanitizeRichText($value) : $this->stripAllTags($value);

            if ($sanitized !== null) {
                $clean[$locale] = $sanitized;
            }
        }

        return $clean === [] ? null : $clean;
    }
}
