<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StorePageSectionRequest;
use App\Http\Requests\Dashboard\UpdatePageSectionRequest;
use App\Models\Page;
use App\Models\PageSection;
use App\Services\Cms\PageSectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sections belonging to a page.
 *
 * Nested under the page in the routing, so every action is scoped to a
 * parent — and guarded below, because Laravel's implicit binding resolves
 * the two parameters INDEPENDENTLY: without the check, a section id from
 * one page would happily load under another page's URL.
 */
class PageSectionController extends Controller
{
    public function __construct(private readonly PageSectionService $writer) {}

    public function create(Page $page): View
    {
        return view('dashboard.page-sections.create', ['page' => $page]);
    }

    public function store(StorePageSectionRequest $request, Page $page): RedirectResponse
    {
        $this->writer->create($page, $request->validated());

        return redirect()
            ->route('dashboard.pages.edit', $page)
            ->with('status', __('dashboard.flash.created'));
    }

    public function edit(Page $page, PageSection $section): View
    {
        $this->assertBelongsToPage($page, $section);

        return view('dashboard.page-sections.edit', ['page' => $page, 'section' => $section]);
    }

    public function update(UpdatePageSectionRequest $request, Page $page, PageSection $section): RedirectResponse
    {
        $this->assertBelongsToPage($page, $section);

        $this->writer->update($section, $request->validated());

        return redirect()
            ->route('dashboard.pages.edit', $page)
            ->with('status', __('dashboard.flash.updated'));
    }

    public function destroy(Page $page, PageSection $section): RedirectResponse
    {
        $this->assertBelongsToPage($page, $section);

        $this->writer->delete($section);

        return redirect()
            ->route('dashboard.pages.edit', $page)
            ->with('status', __('dashboard.flash.deleted'));
    }

    /**
     * 404, not 403: the section genuinely does not exist AT THIS URL, and
     * saying so reveals nothing about what exists elsewhere.
     */
    private function assertBelongsToPage(Page $page, PageSection $section): void
    {
        if ($section->page_id !== $page->getKey()) {
            throw new NotFoundHttpException;
        }
    }
}
