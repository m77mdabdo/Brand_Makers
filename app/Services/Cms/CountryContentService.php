<?php

namespace App\Services\Cms;

use App\Models\Country;

class CountryContentService extends ContentResourceService
{
    protected function model(): string
    {
        return Country::class;
    }

    protected function translatableFields(): array
    {
        return ['name' => false, 'notes' => true];
    }

    protected function imageField(): ?array
    {
        return ['flag_image', 'countries'];
    }
}
