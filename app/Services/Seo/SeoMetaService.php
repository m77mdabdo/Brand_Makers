<?php

namespace App\Services\Seo;

use App\Models\SeoMeta;
use App\Services\Cms\ContentPublishingService;
use App\Services\Cms\HtmlSanitizerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * The write side of the SeoMeta morph relation, shared by every
 * seoMetable resource so the logic is not copy-pasted across four
 * controllers.
 */
class SeoMetaService
{
    public function __construct(
        private readonly ContentPublishingService $images,
        private readonly HtmlSanitizerService $sanitizer,
    ) {}

    /**
     * Creates, updates, or deliberately does nothing.
     *
     * @param  array<string, mixed>  $data  the validated `seo` sub-array
     */
    public function persist(Model $model, array $data, ?UploadedFile $ogImage = null): ?SeoMeta
    {
        $existing = $model->seoMeta()->first();

        $attributes = [
            // Meta tags are plain text by definition — markup in a <title>
            // or a meta description is either escaped noise or an
            // injection attempt.
            'meta_title' => $this->sanitizer->sanitizeTranslations($data['meta_title'] ?? null),
            'meta_description' => $this->sanitizer->sanitizeTranslations($data['meta_description'] ?? null),
            'canonical_url' => $data['canonical_url'] ?? null,
        ];

        if ($ogImage) {
            $attributes['og_image'] = $this->images->replaceImage($ogImage, 'seo', $existing?->og_image);
        }

        // Nothing filled in and no row yet: do NOT create an empty record.
        // The public side already falls back to the record's own content,
        // so a blank SeoMeta row would cost a query and buy nothing.
        if (! $existing && ! $this->hasContent($attributes)) {
            return null;
        }

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return $model->seoMeta()->create($attributes);
    }

    /**
     * Removes the row and its uploaded image.
     *
     * morphOne has no database-level cascade, so without this a
     * hard-deleted record leaves an orphaned seo_meta row pointing at an
     * id that no longer exists — and, worse, one that a future record
     * could reuse.
     */
    public function purge(Model $model): void
    {
        $seoMeta = $model->seoMeta()->first();

        if (! $seoMeta) {
            return;
        }

        $this->images->deleteImage($seoMeta->og_image);
        $seoMeta->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hasContent(array $attributes): bool
    {
        foreach ($attributes as $value) {
            if (is_array($value) ? $value !== [] : (is_string($value) && trim($value) !== '')) {
                return true;
            }
        }

        return false;
    }
}
