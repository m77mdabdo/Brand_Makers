<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreEstimatorGroupRequest;
use App\Http\Requests\Dashboard\UpdateEstimatorGroupRequest;
use App\Models\EstimatorGroup;
use App\Services\Cms\EstimatorGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The estimator's selectors.
 *
 * A group is one dropdown on the public form: its label is what the
 * visitor reads, its `selection` decides whether they may pick one option
 * or several, and its order decides where the selector appears.
 *
 * WHY THIS SCREEN EXISTS. `estimator_items.group` was always a free-form
 * slug an admin could type, but the LABEL lived in a lang file — so a
 * group somebody invented rendered as its raw slug to visitors. Half the
 * feature was manageable and half was not, and the half that was not was
 * the half being read.
 *
 * The controller validates and redirects; every write goes through
 * EstimatorGroupService.
 */
class EstimatorGroupController extends Controller
{
    public function __construct(private readonly EstimatorGroupService $writer) {}

    public function index(): View
    {
        return view('dashboard.estimator-groups.index', [
            // withCount on the slug join, so the admin can see which
            // selectors would render empty — a group with no active items
            // is dropped from the public form entirely.
            'groups' => EstimatorGroup::query()
                ->withCount(['items', 'activeItems'])
                ->ordered()
                ->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.estimator-groups.create');
    }

    public function store(StoreEstimatorGroupRequest $request): RedirectResponse
    {
        $this->writer->create($request->validated());

        return redirect()
            ->route('dashboard.estimator-groups.index')
            ->with('status', __('dashboard.flash.created'));
    }

    public function edit(EstimatorGroup $estimatorGroup): View
    {
        return view('dashboard.estimator-groups.edit', ['group' => $estimatorGroup]);
    }

    public function update(UpdateEstimatorGroupRequest $request, EstimatorGroup $estimatorGroup): RedirectResponse
    {
        $this->writer->update($estimatorGroup, $request->validated());

        return redirect()
            ->route('dashboard.estimator-groups.index')
            ->with('status', __('dashboard.flash.updated'));
    }

    /**
     * Deleting a group does NOT delete its items.
     *
     * The two are joined on a slug, not a foreign key, precisely so that
     * removing a heading cannot take priced rows with it. The orphaned
     * items keep rendering on the public form under their raw slug until
     * the admin moves them, which is visible and recoverable — unlike
     * silently deleting work.
     */
    public function destroy(EstimatorGroup $estimatorGroup): RedirectResponse
    {
        $this->writer->delete($estimatorGroup);

        return redirect()
            ->route('dashboard.estimator-groups.index')
            ->with('status', __('dashboard.flash.deleted'));
    }
}
