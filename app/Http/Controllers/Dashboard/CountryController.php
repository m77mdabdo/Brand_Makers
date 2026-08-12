<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreCountryRequest;
use App\Http\Requests\Dashboard\UpdateCountryRequest;
use App\Models\Country;
use App\Services\Cms\CountryContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The markets the studio works in.
 *
 * The controller validates and redirects; every WRITE goes through
 * CountryContentService, so the sanitising, image handling and SEO
 * persistence rules live in one place rather than being repeated in
 * store() and update().
 */
class CountryController extends Controller
{
    public function __construct(private readonly CountryContentService $writer) {}

    public function index(): View
    {
        return view('dashboard.countries.index', [
            'countries' => Country::query()->orderBy('sort_order')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.countries.create');
    }

    public function store(StoreCountryRequest $request): RedirectResponse
    {
        $this->writer->create(
            $request->safe()->except(['flag_image', 'seo', 'seo_og_image']),
            $request->validated('seo', []),
            $request->file('flag_image'),
            $request->file('seo_og_image'),
        );

        return redirect()
            ->route('dashboard.countries.index')
            ->with('status', __('dashboard.flash.created'));
    }

    public function edit(Country $country): View
    {
        return view('dashboard.countries.edit', ['country' => $country->load('seoMeta')]);
    }

    public function update(UpdateCountryRequest $request, Country $country): RedirectResponse
    {
        $this->writer->update(
            $country,
            $request->safe()->except(['flag_image', 'seo', 'seo_og_image']),
            $request->validated('seo', []),
            $request->file('flag_image'),
            $request->file('seo_og_image'),
        );

        return redirect()
            ->route('dashboard.countries.index')
            ->with('status', __('dashboard.flash.updated'));
    }

    public function destroy(Country $country): RedirectResponse
    {
        $this->writer->delete($country);

        return redirect()
            ->route('dashboard.countries.index')
            ->with('status', __('dashboard.flash.deleted'));
    }
}
