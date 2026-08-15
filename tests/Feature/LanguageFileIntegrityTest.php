<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The language files, as data structures.
 *
 * WHY THIS EXISTS. A duplicate top-level key in a PHP array is not an
 * error — the last one silently wins. Two separate build steps added a
 * `'booking' => [...]` and an `'estimator' => [...]` block to site.php
 * without noticing the homepage already had blocks by those names, so the
 * homepage quietly began rendering the wrong copy; a third did the same to
 * `'tracker'`, whose block held an array the homepage iterates, and that
 * one finally surfaced as a 500 on the busiest page of the site.
 *
 * Nothing caught it: the route sweep only sees status codes, and the
 * homepage tests asserted strings that happened to still resolve. So the
 * property is asserted directly here — a key must have exactly one owner.
 */
class LanguageFileIntegrityTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function languageFiles(): array
    {
        /*
         * dirname() rather than lang_path(): a data provider is static and
         * runs BEFORE the application boots, so the container — and every
         * helper that resolves through it — is not available yet.
         */
        $langPath = dirname(__DIR__, 2).'/lang';

        $cases = [];

        // glob() rather than the File facade, for the same reason: a
        // facade needs a container root, and there is not one yet.
        foreach (glob($langPath.'/*/*.php') ?: [] as $file) {
            $cases[str_replace($langPath.'/', '', $file)] = [$file];
        }

        return $cases;
    }

    /**
     * Reads the SOURCE, not the returned array.
     *
     * require'ing the file would give the post-collapse array, in which
     * the duplicate has already been silently discarded — the very thing
     * being looked for. Counting the declarations in the text is the only
     * way to see it.
     */
    #[DataProvider('languageFiles')]
    public function test_no_top_level_key_is_declared_twice(string $path): void
    {
        $source = (string) file_get_contents($path);

        // Top-level keys only: four spaces of indentation, which is the
        // one level Pint guarantees in these files. A nested duplicate is
        // a different (and far less damaging) mistake.
        preg_match_all("/^    '([a-z0-9_]+)' => /m", $source, $matches);

        $counts = array_count_values($matches[1]);
        $duplicates = array_keys(array_filter($counts, fn (int $count) => $count > 1));

        $this->assertSame(
            [],
            $duplicates,
            basename($path).' declares these keys more than once, and the last one silently wins: '.implode(', ', $duplicates)
        );
    }

    /**
     * Arabic and English must describe the same site.
     *
     * A key present in one file and missing from the other renders as the
     * raw dotted key on the locale that lacks it — "site.tracker.title" in
     * the middle of a page. spatie/laravel-translatable's fallback covers
     * model CONTENT; it does nothing for interface strings.
     */
    public function test_both_locales_declare_the_same_top_level_keys(): void
    {
        foreach (['site', 'dashboard'] as $file) {
            $ar = array_keys(require lang_path("ar/{$file}.php"));
            $en = array_keys(require lang_path("en/{$file}.php"));

            sort($ar);
            sort($en);

            $this->assertSame($en, $ar, "lang/{$file}.php differs between locales.");
        }
    }
}
