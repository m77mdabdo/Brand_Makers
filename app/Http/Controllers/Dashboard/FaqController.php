<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreFaqRequest;
use App\Http\Requests\Dashboard\UpdateFaqRequest;
use App\Models\Faq;
use App\Services\Cms\FaqContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Frequently asked questions.
 *
 * The controller validates and redirects; every WRITE goes through
 * FaqContentService, so the sanitising, image handling and SEO
 * persistence rules live in one place rather than being repeated in
 * store() and update().
 */
class FaqController extends Controller
{
    public function __construct(private readonly FaqContentService $writer) {}

    public function index(): View
    {
        return view('dashboard.faqs.index', [
            'faqs' => Faq::query()->orderBy('sort_order')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.faqs.create');
    }

    public function store(StoreFaqRequest $request): RedirectResponse
    {
        $this->writer->create(
            $request->safe()->except([]),
            [],
            null,
        );

        return redirect()
            ->route('dashboard.faqs.index')
            ->with('status', __('dashboard.flash.created'));
    }

    public function edit(Faq $faq): View
    {
        return view('dashboard.faqs.edit', ['faq' => $faq]);
    }

    public function update(UpdateFaqRequest $request, Faq $faq): RedirectResponse
    {
        $this->writer->update(
            $faq,
            $request->safe()->except([]),
            [],
            null,
        );

        return redirect()
            ->route('dashboard.faqs.index')
            ->with('status', __('dashboard.flash.updated'));
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $this->writer->delete($faq);

        return redirect()
            ->route('dashboard.faqs.index')
            ->with('status', __('dashboard.flash.deleted'));
    }
}
