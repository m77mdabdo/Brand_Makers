<?php

namespace App\Services\Cms;

use App\Models\Article;
use Illuminate\Database\Eloquent\Model;

class ArticleContentService extends ContentResourceService
{
    protected function model(): string
    {
        return Article::class;
    }

    protected function translatableFields(): array
    {
        return ['title' => false, 'excerpt' => false, 'body' => true];
    }

    protected function imageField(): ?array
    {
        return ['cover_image', 'articles'];
    }

    protected function booleanFields(): array
    {
        return ['is_published'];
    }

    /**
     * Publication state, resolved from the two things the form actually
     * asks: "is it published?" and "from when?".
     *
     * THE THREE STATES, and why they are derived here rather than stored
     * as an enum:
     *
     *   draft     is_published = false. published_at is cleared, so a
     *             draft never carries a stale date that would make it go
     *             live the moment someone ticks the box.
     *   scheduled is_published = true, published_at in the future. The
     *             public scope (Article::live) filters on
     *             published_at <= now(), so this is invisible until then
     *             with no cron job involved.
     *   live      is_published = true, published_at now or earlier.
     *
     * Leaving the date blank while publishing means "now" — the common
     * case, and the one an admin should not have to think about.
     */
    protected function sanitize(array $data): array
    {
        $data = parent::sanitize($data);

        if (! ($data['is_published'] ?? false)) {
            $data['published_at'] = null;

            return $data;
        }

        $data['published_at'] = ($data['published_at'] ?? null) ?: now();

        return $data;
    }

    public function unpublish(Article $article): Model
    {
        return $this->update($article, ['is_published' => false]);
    }
}
