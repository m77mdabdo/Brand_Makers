<?php

namespace App\Services\Cms;

use App\Services\Seo\SeoMetaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The write path shared by every simple content resource — Services,
 * Countries, FAQs, Testimonials, Packages.
 *
 * Controllers do not write. They validate, then hand the validated array
 * here. That is what keeps four rules in ONE place instead of repeated
 * across a dozen controller methods:
 *
 *   1. every translatable field is sanitised before it is stored, and
 *      blank locales are dropped (see HtmlSanitizerService for why an
 *      empty string is worse than a missing key);
 *   2. an uploaded image replaces the previous file rather than
 *      accumulating;
 *   3. the SeoMeta morph row is persisted in the same transaction;
 *   4. deleting cleans up whatever the database cannot cascade —
 *      the image on disk, and the SeoMeta row.
 *
 * Subclasses declare WHAT their resource has; none of them reimplements
 * HOW it is written.
 */
abstract class ContentResourceService
{
    public function __construct(
        protected readonly HtmlSanitizerService $sanitizer,
        protected readonly ContentPublishingService $images,
        protected readonly SeoMetaService $seoMeta,
    ) {}

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /**
     * field => true when the field may contain rich HTML, false when it
     * must be reduced to plain text.
     *
     * @return array<string, bool>
     */
    abstract protected function translatableFields(): array;

    /**
     * The column holding an uploaded image path, and the directory it
     * lives in. Null for resources with no image of their own.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function imageField(): ?array
    {
        return null;
    }

    protected function supportsSeo(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $seo
     */
    public function create(array $data, array $seo = [], ?UploadedFile $image = null, ?UploadedFile $ogImage = null): Model
    {
        return DB::transaction(function () use ($data, $seo, $image, $ogImage) {
            $attributes = $this->sanitize($data);

            if ($image && $field = $this->imageField()) {
                $attributes[$field[0]] = $this->images->storeImage($image, $field[1]);
            }

            $record = $this->model()::create($attributes);

            if ($this->supportsSeo()) {
                $this->seoMeta->persist($record, $seo, $ogImage);
            }

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $seo
     */
    public function update(Model $record, array $data, array $seo = [], ?UploadedFile $image = null, ?UploadedFile $ogImage = null): Model
    {
        return DB::transaction(function () use ($record, $data, $seo, $image, $ogImage) {
            $attributes = $this->sanitize($data);

            if ($image && $field = $this->imageField()) {
                $attributes[$field[0]] = $this->images->replaceImage($image, $field[1], $record->{$field[0]});
            }

            $record->update($attributes);

            if ($this->supportsSeo()) {
                $this->seoMeta->persist($record, $seo, $ogImage);
            }

            return $record->refresh();
        });
    }

    /**
     * Soft-deleting models keep their image and SEO row: the record can be
     * restored, and a restored service with no cover and no metadata is
     * only half a restore. Hard deletes clean both up, because nothing
     * else will — morphOne has no DB cascade, and disk files have no
     * foreign keys at all.
     */
    public function delete(Model $record): void
    {
        DB::transaction(function () use ($record) {
            if ($this->softDeletes($record)) {
                $record->delete();

                return;
            }

            if ($this->supportsSeo()) {
                $this->seoMeta->purge($record);
            }

            if ($field = $this->imageField()) {
                $this->images->deleteImage($record->{$field[0]});
            }

            $record->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitize(array $data): array
    {
        foreach ($this->translatableFields() as $field => $richText) {
            // array_key_exists, not isset: a field the admin cleared
            // arrives as null and must be written as null, whereas a field
            // the form never submitted must be left alone.
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $data[$field] = $this->sanitizer->sanitizeTranslations(
                is_array($data[$field]) ? $data[$field] : null,
                $richText,
            );
        }

        // Checkboxes are absent from the payload entirely when unticked,
        // so "not submitted" IS false here rather than "leave alone".
        foreach ($this->booleanFields() as $field) {
            $data[$field] = (bool) ($data[$field] ?? false);
        }

        if (array_key_exists('sort_order', $data)) {
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    protected function booleanFields(): array
    {
        return ['is_active'];
    }

    private function softDeletes(Model $record): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($record), true);
    }
}
