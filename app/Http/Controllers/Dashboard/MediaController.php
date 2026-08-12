<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreMediaRequest;
use App\Models\Media;
use App\Services\Cms\MediaLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The media library — upload, list, delete.
 *
 * No edit: the only mutable field is alt text, and re-uploading is
 * cheaper than a form for one string. Deliberately NOT tied to any
 * resource: these files outlive whatever referenced them, which is why
 * deleting a service must not touch them.
 */
class MediaController extends Controller
{
    public function __construct(private readonly MediaLibraryService $library) {}

    public function index(): View
    {
        return view('dashboard.media.index', [
            'media' => Media::query()->with('uploadedBy')->latest()->paginate(24),
        ]);
    }

    public function store(StoreMediaRequest $request): RedirectResponse
    {
        $this->library->upload(
            $request->file('file'),
            $request->validated('alt_text'),
            $request->user(),
        );

        return redirect()
            ->route('dashboard.media.index')
            ->with('status', __('dashboard.flash.uploaded'));
    }

    public function destroy(Media $medium): RedirectResponse
    {
        $this->library->delete($medium);

        return redirect()
            ->route('dashboard.media.index')
            ->with('status', __('dashboard.flash.deleted'));
    }
}
