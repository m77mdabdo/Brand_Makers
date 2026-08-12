<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ReorderMenuRequest;
use App\Http\Requests\Dashboard\StoreMenuItemRequest;
use App\Http\Requests\Dashboard\UpdateMenuItemRequest;
use App\Models\MenuItem;
use App\Services\Cms\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class MenuItemController extends Controller
{
    public function __construct(private readonly MenuService $menu) {}

    public function index(): View
    {
        return view('dashboard.menu.index', [
            // The WHOLE tree, hidden items included — this is the editor,
            // not the public navbar.
            'items' => MenuItem::topLevel()->ordered()->with('children')->get(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.menu.create', [
            'parents' => $this->parentOptions(),
            'routes' => $this->routeOptions(),
        ]);
    }

    public function store(StoreMenuItemRequest $request): RedirectResponse
    {
        $this->menu->create($request->validated());

        return redirect()
            ->route('dashboard.menu.index')
            ->with('status', __('dashboard.flash.created'));
    }

    public function edit(MenuItem $menu): View
    {
        return view('dashboard.menu.edit', [
            'item' => $menu,
            'parents' => $this->parentOptions($menu),
            'routes' => $this->routeOptions(),
        ]);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menu): RedirectResponse
    {
        $this->menu->update($menu, $request->validated());

        return redirect()
            ->route('dashboard.menu.index')
            ->with('status', __('dashboard.flash.updated'));
    }

    /**
     * System items cannot be deleted — only hidden.
     *
     * They are the core navigation, and an admin who removed them would
     * leave the site with no way to get anywhere. `is_system` is guarded
     * out of $fillable precisely so this check cannot be defeated by a
     * crafted payload flipping the flag first.
     */
    public function destroy(MenuItem $menu): RedirectResponse
    {
        if ($menu->is_system) {
            return redirect()
                ->route('dashboard.menu.index')
                ->with('status', __('dashboard.flash.system_item_protected'));
        }

        $this->menu->delete($menu);

        return redirect()
            ->route('dashboard.menu.index')
            ->with('status', __('dashboard.flash.deleted'));
    }

    public function toggleVisibility(MenuItem $menu): RedirectResponse
    {
        $this->menu->toggleVisibility($menu);

        return redirect()
            ->route('dashboard.menu.index')
            ->with('status', __('dashboard.flash.visibility_updated'));
    }

    public function reorder(ReorderMenuRequest $request): RedirectResponse
    {
        $this->menu->reorder($request->validated('items'));

        return redirect()
            ->route('dashboard.menu.index')
            ->with('status', __('dashboard.flash.reordered'));
    }

    /**
     * Top-level items only — nesting is capped at one level, so a child
     * can never be offered as a parent. Editing an item also excludes
     * itself.
     *
     * @return Collection<int, MenuItem>
     */
    private function parentOptions(?MenuItem $except = null): Collection
    {
        return MenuItem::topLevel()
            ->ordered()
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get();
    }

    /**
     * The route picker.
     *
     * Whitelisted AND registered: the whitelist says which routes the
     * navbar is ALLOWED to reach, and Route::has says which actually
     * exist yet. Offering an unbuilt route would let an admin create a
     * menu entry that renders as nothing — see MenuItem::href.
     *
     * @return array<string, string>
     */
    private function routeOptions(): array
    {
        $options = [];

        foreach (MenuItem::ROUTE_WHITELIST as $name => $labelKey) {
            $options[$name] = Route::has($name)
                ? __($labelKey)
                : __($labelKey).' — '.__('dashboard.menu.route_missing');
        }

        return $options;
    }
}
