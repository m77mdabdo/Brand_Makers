<?php

namespace App\Services\Cms;

use App\Models\Package;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PackageContentService extends ContentResourceService
{
    protected function model(): string
    {
        return Package::class;
    }

    /**
     * Both plain text. A package name is a heading and its description is
     * one paragraph under the price — neither is a place for markup, and
     * the public card has no prose container to render it in.
     */
    protected function translatableFields(): array
    {
        return ['name' => false, 'description' => false];
    }

    protected function booleanFields(): array
    {
        return ['is_active', 'is_popular'];
    }

    /** Packages have no image of their own — the card is typographic. */
    protected function imageField(): ?array
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $seo
     */
    public function create(array $data, array $seo = [], ?UploadedFile $image = null, ?UploadedFile $ogImage = null): Model
    {
        return DB::transaction(function () use ($data, $seo, $image, $ogImage) {
            /** @var Package $package */
            $package = parent::create($data, $seo, $image, $ogImage);

            $this->enforceSinglePopular($package);

            return $package;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $seo
     */
    public function update(Model $record, array $data, array $seo = [], ?UploadedFile $image = null, ?UploadedFile $ogImage = null): Model
    {
        return DB::transaction(function () use ($record, $data, $seo, $image, $ogImage) {
            /** @var Package $package */
            $package = parent::update($record, $data, $seo, $image, $ogImage);

            $this->enforceSinglePopular($package);

            return $package->refresh();
        });
    }

    /**
     * "Most popular" means one package, so marking a second clears the
     * first.
     *
     * The column allows any number of true rows and the public card just
     * badges each one — two packages both labelled "الأكثر طلباً / Most
     * popular" is not a state anybody would choose on purpose, it is what
     * happens when someone promotes a new package and forgets the old one.
     * Enforced here rather than validated in the request, because the fix
     * is obvious and doing it silently is kinder than an error telling an
     * admin to go and untick something else first.
     */
    private function enforceSinglePopular(Package $package): void
    {
        if (! $package->is_popular) {
            return;
        }

        Package::query()
            ->whereKeyNot($package->getKey())
            ->where('is_popular', true)
            ->update(['is_popular' => false]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitize(array $data): array
    {
        $data = parent::sanitize($data);

        if (array_key_exists('features', $data)) {
            $data['features'] = $this->featureList($data['features']);
        }

        /*
         * Blank means "on request", which is a different fact from 0.00
         * and the reason the column is nullable. An empty input must
         * therefore clear the price rather than be read as free work.
         */
        if (array_key_exists('price', $data)) {
            $data['price'] = $this->price($data['price']);
        }

        return $data;
    }

    /**
     * Turns the editor's two textareas into the stored list.
     *
     * THE SHAPE. `features` is a LIST of per-locale maps —
     * [{"ar": "…", "en": "…"}, …] — not a translatable column, because
     * the list's LENGTH is part of the content. Storing {"ar": […],
     * "en": […]} would let the two locales end up with different numbers
     * of bullets and no way to pair them (see the Package docblock).
     *
     * THE PAIRING. One feature per line, and line N in Arabic is the same
     * feature as line N in English. Arabic drives the count, matching the
     * Arabic-primary rule the whole project follows: an English line with
     * no Arabic counterpart is dropped rather than creating a bullet that
     * renders blank on the default locale.
     *
     * @param  mixed  $value  the submitted ['ar' => "…\n…", 'en' => "…"]
     * @return list<array<string, string>>|null
     */
    private function featureList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $arabic = $this->lines($value['ar'] ?? null);
        $english = $this->lines($value['en'] ?? null);

        $features = [];

        foreach ($arabic as $index => $line) {
            $feature = ['ar' => $line];

            if (isset($english[$index]) && $english[$index] !== '') {
                $feature['en'] = $english[$index];
            }

            $features[] = $feature;
        }

        return $features === [] ? null : $features;
    }

    /**
     * @return list<string>
     */
    private function lines(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        /*
         * THE /u MODIFIER IS LOAD-BEARING, not decoration.
         *
         * Without it PCRE walks the subject as bytes, and `\R` — "any
         * line break" — matches U+0085 NEL, whose byte 0x85 occurs INSIDE
         * ordinary Arabic characters. Splitting "تصميم الشعار" without
         * /u returns ["تص\xd8", "ي\xd9", " الشعار"]: the string is torn
         * mid-character and the halves are no longer valid UTF-8, so the
         * json cast then throws "Malformed UTF-8 characters" on save.
         *
         * Verified: preg_split('/\R/', 'تصميم الشعار') yields 3 fragments,
         * two of which fail mb_check_encoding(); with /u it yields 1.
         */
        $lines = preg_split('/\R/u', $value) ?: [];

        // Blank lines are dropped rather than becoming empty bullets, but
        // this reindexes — which is exactly why the two textareas must be
        // kept in step by the admin, and why the editor says so.
        return array_values(array_filter(
            array_map(fn (string $line) => trim($line), $lines),
            fn (string $line) => $line !== '',
        ));
    }

    private function price(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }
}
