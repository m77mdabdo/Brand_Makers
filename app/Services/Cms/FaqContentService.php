<?php

namespace App\Services\Cms;

use App\Models\Faq;

class FaqContentService extends ContentResourceService
{
    protected function model(): string
    {
        return Faq::class;
    }

    protected function translatableFields(): array
    {
        return ['question' => false, 'answer' => true];
    }

    /** FAQs have no SeoMeta relation — they are not addressable pages. */
    protected function supportsSeo(): bool
    {
        return false;
    }
}
