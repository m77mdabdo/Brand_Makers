<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Country;
use App\Models\EstimatorItem;
use App\Models\Faq;
use App\Models\Package;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\Testimonial;
use App\Services\Cms\HtmlSanitizerService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Fills the English side of every translatable database field that has
 * Arabic content and no English content.
 *
 * PORTED FROM the Bawabat Taasees project, whose implementation this
 * mirrors. Only the mechanism is shared — the dictionary below is written
 * for Creative Mark's own copy, and no Arabic or English string was taken
 * from that project's content.
 *
 * WHAT THIS IS NOT. There is no machine-translation API here, and none was
 * asked for. The dictionary is a hand-authored lookup keyed by the EXACT
 * Arabic source string. That works because this project's content is
 * authored once through seeders rather than typed freely by visitors, so
 * the set of strings is closed and known.
 *
 * WHY A COMMAND RATHER THAN 'en' KEYS IN THE SEEDERS. It keeps every
 * English rendering of the site's copy in ONE file, where it can be read
 * as a translation — side by side with its source — instead of scattered
 * across nine seeders where the two languages drift apart a line at a
 * time. It also makes a gap loud: a string with no entry is REPORTED as
 * "no translation available" rather than silently left blank or guessed
 * at, so new Arabic content cannot quietly ship with an empty English
 * page behind it.
 *
 * RUN ORDER. DatabaseSeeder calls this last, after every content seeder,
 * so `migrate:fresh --seed` produces a bilingual site in one step. Run it
 * by hand after editing Arabic content in the dashboard.
 *
 * HTML bodies are stored here WITH their wrapping tags, matching the
 * source's structure, and still pass through HtmlSanitizerService before
 * being saved — a translation authored here gets no bypass of the
 * sanitizer that every other HTML write in this project goes through.
 *
 * SCOPE. Database content only. The static UI chrome in lang/en/site.php
 * is a PHP file, not a model, and is not reachable from here.
 */
class TranslateContentToEnglish extends Command
{
    protected $signature = 'content:translate-to-english
                            {--force : Overwrite English content that already exists, instead of skipping it}';

    protected $description = 'Writes English translations into every translatable field that has Arabic content and no English content.';

    /**
     * Arabic source string => English translation.
     *
     * Keyed by exact match. Entries are grouped by the seeder that
     * produces them, so a change to a seeder has one obvious place to be
     * mirrored.
     *
     * BRAND NAME. "صنّاع العلامات التجارية" renders as "Creative Mark"
     * throughout — the studio's own Latin name, already set in
     * site.brand.name_latin, not a literal translation of the Arabic.
     *
     * @var array<string, string>
     */
    private array $translations = [
        //
        // Entries are added alongside the content that needs them. An
        // empty dictionary is a valid state: every field then reports as
        // "no translation available", which is exactly the signal this
        // command exists to give.
        //
    ];

    /**
     * Pages whose Arabic copy makes claims about Saudi regulation —
     * foreign-ownership limits, licensing, tax and zakat obligations.
     *
     * Their English translations are exactly as provisional as the Arabic
     * originals and carry the same need for professional review. Reported
     * at the end of every run so a reader never mistakes "translated" for
     * "verified".
     *
     * @var list<string>
     */
    private array $regulatoryClaimSlugs = [
        'why-invest-saudi-arabia',
        'activities',
    ];

    private int $totalTranslated = 0;

    private int $totalSkippedExisting = 0;

    /** @var list<string> */
    private array $untranslatable = [];

    public function handle(HtmlSanitizerService $sanitizer): int
    {
        /*
         * Laravel reuses one resolved Command instance across repeated
         * Artisan::call() invocations in a single process — the seeder
         * calls this, and a test may call it again straight afterwards.
         * Reset explicitly rather than assuming a fresh object.
         */
        $this->totalTranslated = 0;
        $this->totalSkippedExisting = 0;
        $this->untranslatable = [];

        $force = (bool) $this->option('force');

        $summary = [];

        $summary['Page'] = $this->translateModel(Page::class, ['title', 'body', 'meta_title', 'meta_description'], $force, htmlFields: ['body']);
        $summary['PageSection'] = $this->translatePageSections($force);
        $summary['Service'] = $this->translateModel(Service::class, ['name', 'summary', 'body', 'requirements', 'process'], $force, htmlFields: ['body']);
        $summary['Package'] = $this->translatePackages($force);
        $summary['EstimatorItem'] = $this->translateModel(EstimatorItem::class, ['label'], $force);
        $summary['Country'] = $this->translateModel(Country::class, ['name', 'notes'], $force);
        $summary['Faq'] = $this->translateModel(Faq::class, ['question', 'answer'], $force);
        $summary['Article'] = $this->translateModel(Article::class, ['title', 'excerpt', 'body'], $force, htmlFields: ['body']);
        $summary['Testimonial'] = $this->translateModel(Testimonial::class, ['quote'], $force);
        $summary['SeoMeta'] = $this->translateModel(SeoMeta::class, ['meta_title', 'meta_description'], $force);

        $this->printSummary($summary);

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $fields
     * @param  list<string>  $htmlFields
     * @return array<string, int>
     */
    private function translateModel(string $modelClass, array $fields, bool $force, array $htmlFields = []): array
    {
        $stats = $this->emptyStats();

        foreach ($modelClass::all() as $model) {
            $dirty = false;

            foreach ($fields as $field) {
                // false: the raw Arabic with no fallback. With the
                // fallback on, a field that has NO Arabic would read as
                // whatever locale answered, and we would translate a
                // string the source never contained.
                $ar = $model->getTranslation($field, 'ar', false);

                if (empty($ar)) {
                    continue;
                }

                if (! empty($model->getTranslation($field, 'en', false)) && ! $force) {
                    $stats['skipped_existing']++;

                    continue;
                }

                $en = $this->translations[$ar] ?? null;

                if ($en === null) {
                    $stats['skipped_no_translation']++;
                    $this->untranslatable[] = class_basename($modelClass).'#'.$model->getKey()." [{$field}]: ".Str::limit($ar, 60);

                    continue;
                }

                if (in_array($field, $htmlFields, true)) {
                    $en = app(HtmlSanitizerService::class)->sanitizeRichText($en);
                }

                $model->setTranslation($field, 'en', $en);
                $dirty = true;
                $stats['translated']++;
            }

            if ($dirty) {
                $model->save();
            }
        }

        $this->tally($stats);

        return $stats;
    }

    /**
     * Packages carry a translatable name and description AND a `features`
     * JSON array whose every element is its own {ar, en} pair — a shape
     * spatie/laravel-translatable knows nothing about, so it is walked by
     * hand here.
     *
     * @return array<string, int>
     */
    private function translatePackages(bool $force): array
    {
        $stats = $this->translateModel(Package::class, ['name', 'description'], $force);

        foreach (Package::all() as $package) {
            $features = $package->features;

            if (! is_array($features)) {
                continue;
            }

            $dirty = false;

            foreach ($features as $index => $feature) {
                $ar = $feature['ar'] ?? null;

                if (! is_string($ar) || $ar === '') {
                    continue;
                }

                if (! empty($feature['en']) && ! $force) {
                    $stats['skipped_existing']++;
                    $this->totalSkippedExisting++;

                    continue;
                }

                $en = $this->translations[$ar] ?? null;

                if ($en === null) {
                    $stats['skipped_no_translation']++;
                    $this->untranslatable[] = "Package#{$package->getKey()} [features.{$index}]: ".Str::limit($ar, 60);

                    continue;
                }

                $features[$index]['en'] = $en;
                $dirty = true;
                $stats['translated']++;
                $this->totalTranslated++;
            }

            if ($dirty) {
                $package->features = $features;
                $package->save();
            }
        }

        return $stats;
    }

    /**
     * PageSection.content is a single JSON blob holding title/description/
     * icon per locale, not a spatie translatable field — there is no
     * getTranslation()/setTranslation() to call, so the array is read and
     * written directly.
     *
     * @return array<string, int>
     */
    private function translatePageSections(bool $force): array
    {
        $stats = $this->emptyStats();

        foreach (PageSection::all() as $section) {
            $content = $section->content;
            $dirty = false;

            foreach (['title', 'description'] as $field) {
                $ar = $content[$field]['ar'] ?? null;

                if (empty($ar)) {
                    continue;
                }

                if (! empty($content[$field]['en']) && ! $force) {
                    $stats['skipped_existing']++;

                    continue;
                }

                $en = $this->translations[$ar] ?? null;

                if ($en === null) {
                    $stats['skipped_no_translation']++;
                    $this->untranslatable[] = 'PageSection#'.$section->getKey()." [{$field}]: ".Str::limit($ar, 60);

                    continue;
                }

                $content[$field]['en'] = $en;
                $dirty = true;
                $stats['translated']++;
            }

            if ($dirty) {
                $section->content = $content;
                $section->save();
            }
        }

        $this->tally($stats);

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    private function emptyStats(): array
    {
        return ['translated' => 0, 'skipped_existing' => 0, 'skipped_no_translation' => 0];
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function tally(array $stats): void
    {
        $this->totalTranslated += $stats['translated'];
        $this->totalSkippedExisting += $stats['skipped_existing'];
    }

    /**
     * @param  array<string, array<string, int>>  $summary
     */
    private function printSummary(array $summary): void
    {
        $this->newLine();
        $this->info('English translation summary:');

        $this->table(
            ['Model', 'Translated', 'Skipped (already English)', 'Skipped (no translation available)'],
            collect($summary)->map(fn (array $stats, string $model) => [
                $model,
                $stats['translated'],
                $stats['skipped_existing'],
                $stats['skipped_no_translation'],
            ])->values()->all()
        );

        if ($this->untranslatable !== []) {
            $this->newLine();
            $this->warn('Arabic content with no entry in this command\'s dictionary. These need authoring, not guessing:');

            foreach ($this->untranslatable as $line) {
                $this->line("  - {$line}");
            }
        }

        $this->newLine();
        $this->line("Total fields translated: {$this->totalTranslated}");
        $this->line("Total fields that already had English (skipped): {$this->totalSkippedExisting}");

        $this->newLine();
        $this->warn(
            'Reminder: the copy on ['.implode(', ', $this->regulatoryClaimSlugs).'] describes Saudi '
            .'regulation — ownership rules, licensing, tax and zakat. The English here is a translation '
            .'of that copy, not a verification of it, and is exactly as provisional as the Arabic.'
        );
    }
}
