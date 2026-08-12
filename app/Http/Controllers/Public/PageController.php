<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The seeded editorial pages (about, our-process, privacy policy, terms).
 *
 * One action per route rather than a single {slug} catch-all, because the
 * slugs are fixed, they are referenced by name from the footer and the
 * navigation whitelist, and a catch-all would let any Page row become a
 * URL the routing layer never sanctioned.
 */
class PageController extends Controller
{
    public function about(): View
    {
        return $this->show('about');
    }

    public function ourProcess(): View
    {
        return $this->show('our-process');
    }

    public function privacyPolicy(): View
    {
        return $this->show('privacy-policy');
    }

    public function termsAndConditions(): View
    {
        return $this->show('terms-and-conditions');
    }

    private function show(string $slug): View
    {
        $page = Page::query()
            ->published()
            ->where('slug', $slug)
            ->with(['sections' => fn ($query) => $query->active()->ordered(), 'seoMeta'])
            ->first();

        // An unpublished or not-yet-seeded page is a 404, not a blank
        // screen — the route exists, the content does not.
        if (! $page) {
            throw new NotFoundHttpException;
        }

        return view('public.page', ['page' => $page]);
    }
}
