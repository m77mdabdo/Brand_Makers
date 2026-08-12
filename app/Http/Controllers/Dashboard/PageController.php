<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdatePageRequest;
use App\Models\Page;
use App\Services\Cms\PageContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The site's fixed pages — index / edit / update ONLY.
 *
 * No create and no destroy, deliberately. These four pages are seeded and
 * referenced by NAME from the footer, the navigation whitelist and the
 * sitemap. A fifth page created here would have no route to live at, and
 * deleting one would 500 every view that links to it.
 */
class PageController extends Controller
{
    public function __construct(private readonly PageContentService $writer) {}

    public function index(): View
    {
        return view('dashboard.pages.index', [
            'pages' => Page::query()->withCount('sections')->orderBy('slug')->get(),
        ]);
    }

    public function edit(Page $page): View
    {
        return view('dashboard.pages.edit', [
            'page' => $page->load(['seoMeta', 'sections' => fn ($q) => $q->ordered()]),
        ]);
    }

    public function update(UpdatePageRequest $request, Page $page): RedirectResponse
    {
        $this->writer->update(
            $page,
            $request->safe()->except(['seo', 'seo_og_image']),
            $request->validated('seo', []),
            null,
            $request->file('seo_og_image'),
        );

        return redirect()
            ->route('dashboard.pages.edit', $page)
            ->with('status', __('dashboard.flash.updated'));
    }
}
