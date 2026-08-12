<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreTestimonialRequest;
use App\Http\Requests\Dashboard\UpdateTestimonialRequest;
use App\Models\Testimonial;
use App\Services\Cms\TestimonialContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Client testimonials.
 *
 * The controller validates and redirects; every WRITE goes through
 * TestimonialContentService, so the sanitising, image handling and SEO
 * persistence rules live in one place rather than being repeated in
 * store() and update().
 */
class TestimonialController extends Controller
{
    public function __construct(private readonly TestimonialContentService $writer) {}

    public function index(): View
    {
        return view('dashboard.testimonials.index', [
            'testimonials' => Testimonial::query()->orderBy('sort_order')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.testimonials.create');
    }

    public function store(StoreTestimonialRequest $request): RedirectResponse
    {
        $this->writer->create(
            $request->safe()->except(['avatar']),
            [],
            $request->file('avatar'),
        );

        return redirect()
            ->route('dashboard.testimonials.index')
            ->with('status', __('dashboard.flash.created'));
    }

    public function edit(Testimonial $testimonial): View
    {
        return view('dashboard.testimonials.edit', ['testimonial' => $testimonial]);
    }

    public function update(UpdateTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $this->writer->update(
            $testimonial,
            $request->safe()->except(['avatar']),
            [],
            $request->file('avatar'),
        );

        return redirect()
            ->route('dashboard.testimonials.index')
            ->with('status', __('dashboard.flash.updated'));
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $this->writer->delete($testimonial);

        return redirect()
            ->route('dashboard.testimonials.index')
            ->with('status', __('dashboard.flash.deleted'));
    }
}
