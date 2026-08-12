<?php

namespace App\Services\Cms;

use App\Models\Testimonial;

class TestimonialContentService extends ContentResourceService
{
    protected function model(): string
    {
        return Testimonial::class;
    }

    /**
     * The quote is plain text. A client quote with markup in it is either
     * a paste artefact or an injection attempt; neither belongs on the
     * homepage.
     */
    protected function translatableFields(): array
    {
        return ['quote' => false];
    }

    protected function imageField(): ?array
    {
        return ['avatar', 'testimonials'];
    }

    protected function supportsSeo(): bool
    {
        return false;
    }
}
