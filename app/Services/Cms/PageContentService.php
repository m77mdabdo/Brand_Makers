<?php

namespace App\Services\Cms;

use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Pages are EDIT-ONLY.
 *
 * The four pages this site needs are seeded and referenced by name from
 * the footer, the navigation whitelist and the sitemap. Letting an admin
 * create a fifth would produce a Page with no route, and letting them
 * delete one would 500 every view that links to it — so the controller
 * exposes only index/edit/update and this service has no create() or
 * delete() to call.
 */
class PageContentService extends ContentResourceService
{
    protected function model(): string
    {
        return Page::class;
    }

    protected function translatableFields(): array
    {
        return [
            'title' => false,
            'body' => true,
            'meta_title' => false,
            'meta_description' => false,
        ];
    }

    protected function booleanFields(): array
    {
        return ['is_published'];
    }

    public function create(array $data, array $seo = [], ?UploadedFile $image = null, ?UploadedFile $ogImage = null): Model
    {
        throw new \LogicException('Pages are seeded, not created from the dashboard.');
    }

    public function delete(Model $record): void
    {
        throw new \LogicException('Pages are referenced by name from routes and views; deleting one would break them.');
    }
}
