<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreEstimatorItemRequest;
use App\Http\Requests\Dashboard\UpdateEstimatorItemRequest;
use App\Models\EstimatorItem;
use App\Services\Cms\EstimatorItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The estimator's price list.
 *
 * THE PRICES LIVE HERE AND NOWHERE ELSE. Nothing in the application
 * hardcodes a figure: the seeder ships this catalogue priced at 0.00 and
 * inactive, and the studio sets what it actually charges on this screen.
 * Until it does, the public estimator says its pricing is in preparation
 * rather than quoting anything.
 *
 * The controller validates and redirects; every write goes through
 * EstimatorItemService.
 */
class EstimatorItemController extends Controller
{
    public function __construct(private readonly EstimatorItemService $writer) {}

    public function index(): View
    {
        return view('dashboard.estimator-items.index', [
            'items' => EstimatorItem::query()->ordered()->paginate(30),
            // Surfaced so an admin can see at a glance whether the public
            // estimator is open at all — an "everything is inactive" state
            // is otherwise only visible by reading every row.
            'activeCount' => EstimatorItem::query()->active()->count(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.estimator-items.create', [
            'groups' => $this->knownGroups(),
        ]);
    }

    public function store(StoreEstimatorItemRequest $request): RedirectResponse
    {
        $this->writer->create($request->validated());

        return redirect()
            ->route('dashboard.estimator-items.index')
            ->with('status', __('dashboard.flash.created'));
    }

    public function edit(EstimatorItem $estimatorItem): View
    {
        return view('dashboard.estimator-items.edit', [
            'item' => $estimatorItem,
            'groups' => $this->knownGroups(),
        ]);
    }

    public function update(UpdateEstimatorItemRequest $request, EstimatorItem $estimatorItem): RedirectResponse
    {
        $this->writer->update($estimatorItem, $request->validated());

        return redirect()
            ->route('dashboard.estimator-items.index')
            ->with('status', __('dashboard.flash.updated'));
    }

    public function destroy(EstimatorItem $estimatorItem): RedirectResponse
    {
        $this->writer->delete($estimatorItem);

        return redirect()
            ->route('dashboard.estimator-items.index')
            ->with('status', __('dashboard.flash.deleted'));
    }

    /**
     * The groups already in use, offered as suggestions.
     *
     * A datalist rather than a select: `group` is a free-form slug on
     * purpose (a studio adding "motion" should not need a deployment), so
     * this speeds up the common case without closing the field.
     *
     * @return array<int, string>
     */
    private function knownGroups(): array
    {
        return EstimatorItem::query()
            ->select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->all();
    }
}
