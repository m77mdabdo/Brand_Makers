<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StorePackageRequest;
use App\Http\Requests\Dashboard\UpdatePackageRequest;
use App\Models\Package;
use App\Services\Cms\PackageContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The pricing catalogue.
 *
 * THE PRICES LIVE HERE AND NOWHERE ELSE. PackageSeeder ships all four
 * packages with price = null ("on request") because inventing figures the
 * studio never agreed would put them on the most commercial page of the
 * site. This screen is what turns that into a real price list — and until
 * it existed, pricing a package required database access.
 *
 * The controller validates and redirects; every write goes through
 * PackageContentService.
 */
class PackageController extends Controller
{
    public function __construct(private readonly PackageContentService $writer) {}

    public function index(): View
    {
        return view('dashboard.packages.index', [
            'packages' => Package::query()->withCount('orders')->ordered()->paginate(20),
            // Surfaced so an admin can see at a glance that the public
            // pricing page is still quoting "on request" for everything —
            // otherwise that state is only visible by reading every row.
            'unpricedCount' => Package::query()->whereNull('price')->count(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.packages.create');
    }

    public function store(StorePackageRequest $request): RedirectResponse
    {
        $this->writer->create(
            $request->safe()->except(['seo', 'seo_og_image']),
            $request->validated('seo', []),
            null,
            $request->file('seo_og_image'),
        );

        return redirect()
            ->route('dashboard.packages.index')
            ->with('status', __('dashboard.flash.created'));
    }

    public function edit(Package $package): View
    {
        return view('dashboard.packages.edit', ['package' => $package->load('seoMeta')]);
    }

    public function update(UpdatePackageRequest $request, Package $package): RedirectResponse
    {
        $this->writer->update(
            $package,
            $request->safe()->except(['seo', 'seo_og_image']),
            $request->validated('seo', []),
            null,
            $request->file('seo_og_image'),
        );

        return redirect()
            ->route('dashboard.packages.index')
            ->with('status', __('dashboard.flash.updated'));
    }

    /**
     * Soft-deletes. A package is referenced by orders and by package
     * enquiries, both of which resolve withTrashed() — so retiring one
     * keeps every historical record naming what was actually bought.
     */
    public function destroy(Package $package): RedirectResponse
    {
        $this->writer->delete($package);

        return redirect()
            ->route('dashboard.packages.index')
            ->with('status', __('dashboard.flash.deleted'));
    }
}
