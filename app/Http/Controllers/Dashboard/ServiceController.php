<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreServiceRequest;
use App\Http\Requests\Dashboard\UpdateServiceRequest;
use App\Models\Service;
use App\Services\Cms\ServiceContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The service catalogue.
 *
 * The controller validates and redirects; every WRITE goes through
 * ServiceContentService, so the sanitising, image handling and SEO
 * persistence rules live in one place rather than being repeated in
 * store() and update().
 */
class ServiceController extends Controller
{
    public function __construct(private readonly ServiceContentService $writer) {}

    public function index(): View
    {
        return view('dashboard.services.index', [
            'services' => Service::query()->withCount('leads')->orderBy('sort_order')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.services.create');
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $this->writer->create(
            $request->safe()->except(['cover_image', 'seo', 'seo_og_image']),
            $request->validated('seo', []),
            $request->file('cover_image'),
            $request->file('seo_og_image'),
        );

        return redirect()
            ->route('dashboard.services.index')
            ->with('status', __('dashboard.flash.created'));
    }

    public function edit(Service $service): View
    {
        return view('dashboard.services.edit', ['service' => $service->load('seoMeta')]);
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $this->writer->update(
            $service,
            $request->safe()->except(['cover_image', 'seo', 'seo_og_image']),
            $request->validated('seo', []),
            $request->file('cover_image'),
            $request->file('seo_og_image'),
        );

        return redirect()
            ->route('dashboard.services.index')
            ->with('status', __('dashboard.flash.updated'));
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->writer->delete($service);

        return redirect()
            ->route('dashboard.services.index')
            ->with('status', __('dashboard.flash.deleted'));
    }
}
