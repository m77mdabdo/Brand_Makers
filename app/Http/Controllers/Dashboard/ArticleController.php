<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreArticleRequest;
use App\Http\Requests\Dashboard\UpdateArticleRequest;
use App\Models\Article;
use App\Services\Cms\ArticleContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The blog.
 *
 * Publication state (draft / scheduled / live) is derived by
 * ArticleContentService from the two things the form asks; see that class
 * for why it is not stored as a third column.
 */
class ArticleController extends Controller
{
    public function __construct(private readonly ArticleContentService $writer) {}

    public function index(): View
    {
        return view('dashboard.articles.index', [
            // withCount so the index can show a moderation queue size
            // without an N+1 across the page.
            'articles' => Article::query()
                ->withCount(['comments', 'comments as pending_comments_count' => fn ($q) => $q->pending()])
                ->latest('published_at')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.articles.create');
    }

    public function store(StoreArticleRequest $request): RedirectResponse
    {
        $this->writer->create(
            $request->safe()->except(['cover_image', 'seo', 'seo_og_image']),
            $request->validated('seo', []),
            $request->file('cover_image'),
            $request->file('seo_og_image'),
        );

        return redirect()
            ->route('dashboard.articles.index')
            ->with('status', __('dashboard.flash.created'));
    }

    public function edit(Article $article): View
    {
        return view('dashboard.articles.edit', ['article' => $article->load('seoMeta')]);
    }

    public function update(UpdateArticleRequest $request, Article $article): RedirectResponse
    {
        $this->writer->update(
            $article,
            $request->safe()->except(['cover_image', 'seo', 'seo_og_image']),
            $request->validated('seo', []),
            $request->file('cover_image'),
            $request->file('seo_og_image'),
        );

        return redirect()
            ->route('dashboard.articles.index')
            ->with('status', __('dashboard.flash.updated'));
    }

    public function destroy(Article $article): RedirectResponse
    {
        $this->writer->delete($article);

        return redirect()
            ->route('dashboard.articles.index')
            ->with('status', __('dashboard.flash.deleted'));
    }
}
