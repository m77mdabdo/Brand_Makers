<?php

namespace App\Services\Cms;

use App\Models\Page;
use App\Models\PageSection;
use App\Services\Seo\SeoMetaService;

/**
 * Page sections store their content as ONE json blob rather than as
 * translatable columns, because a section's shape varies by key — some
 * carry an icon, some do not — and the locale nesting lives inside the
 * blob.
 *
 * That means the form cannot post straight into a column. It posts flat
 * fields (title_ar, title_en, description_ar, …) and this service REPACKS
 * them into the nested {title: {ar, en}, description: {ar, en}, icon}
 * shape the model's accessors read back. Doing it here rather than in the
 * controller keeps the packing and the PageSection accessors as one
 * matched pair.
 */
class PageSectionService
{
    public function __construct(
        private readonly HtmlSanitizerService $sanitizer,
        private readonly SeoMetaService $seoMeta,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Page $page, array $data): PageSection
    {
        return $page->sections()->create($this->attributes($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PageSection $section, array $data): PageSection
    {
        $section->update($this->attributes($data));

        return $section->refresh();
    }

    public function delete(PageSection $section): void
    {
        $section->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'key' => $data['key'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'content' => $this->packContent($data),
        ];
    }

    /**
     * Flat form fields → the nested blob.
     *
     * Blank locales are dropped for the same reason they are everywhere
     * else: an empty string is a present value, so it would beat the
     * Arabic fallback and render as a gap in English.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function packContent(array $data): array
    {
        $content = [];

        foreach (['title' => false, 'description' => true] as $field => $richText) {
            $values = $this->sanitizer->sanitizeTranslations([
                'ar' => $data[$field.'_ar'] ?? null,
                'en' => $data[$field.'_en'] ?? null,
            ], $richText);

            if ($values !== null) {
                $content[$field] = $values;
            }
        }

        $icon = $this->sanitizer->stripAllTags($data['icon'] ?? null);

        if ($icon !== null) {
            $content['icon'] = $icon;
        }

        return $content;
    }
}
