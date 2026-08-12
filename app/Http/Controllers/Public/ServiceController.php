<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('public.services.index', [
            'services' => Service::active()->ordered()->get(),
        ]);
    }

    public function show(Service $service): View
    {
        /*
         * 404, not a redirect and not a rendered-but-empty page.
         *
         * Route model binding resolves by slug WITHOUT the active scope —
         * it is a plain where + firstOrFail — so an admin who deactivates
         * a service would otherwise leave its URL publicly readable. The
         * status has to be 404 rather than 403 because "this is not
         * published" and "this does not exist" are the same fact to a
         * visitor, and a 403 would confirm the slug is real.
         */
        abort_unless($service->is_active, 404);

        return view('public.services.show', [
            'service' => $service->load('seoMeta'),
            // Cross-links at the foot of the page. Excludes the one being
            // read, and bounded — this is a footer, not a second index.
            'others' => Service::active()
                ->ordered()
                ->whereKeyNot($service->getKey())
                ->limit(3)
                ->get(),
        ]);
    }
}
