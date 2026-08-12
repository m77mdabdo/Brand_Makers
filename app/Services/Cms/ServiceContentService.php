<?php

namespace App\Services\Cms;

use App\Models\Service;

class ServiceContentService extends ContentResourceService
{
    protected function model(): string
    {
        return Service::class;
    }

    /**
     * body / requirements / process carry rich HTML; name and summary are
     * reduced to plain text even though they arrive in the same
     * submission — a heading is not a place for markup.
     */
    protected function translatableFields(): array
    {
        return [
            'name' => false,
            'summary' => false,
            'body' => true,
            'requirements' => true,
            'process' => true,
        ];
    }

    protected function imageField(): ?array
    {
        return ['cover_image', 'services'];
    }

    protected function booleanFields(): array
    {
        return ['is_active', 'is_flagship'];
    }
}
