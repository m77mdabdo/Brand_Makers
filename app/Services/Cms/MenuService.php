<?php

namespace App\Services\Cms;

use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

/**
 * Owns every write to menu_items, so the nesting and ordering invariants
 * live in one place rather than being re-derived in each controller
 * action.
 */
class MenuService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MenuItem
    {
        $data = $this->normalise($data);

        // New items land at the END of their own level, rather than
        // colliding with an existing sort_order.
        $data['sort_order'] = $this->nextSortOrder($data['parent_id'] ?? null);

        return MenuItem::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MenuItem $item, array $data): MenuItem
    {
        $data = $this->normalise($data);

        // Moving between levels puts the item at the end of the new one.
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== $item->parent_id) {
            $data['sort_order'] = $this->nextSortOrder($data['parent_id']);
        }

        $item->update($data);

        return $item->refresh();
    }

    /**
     * Children are REPARENTED to top level, never deleted.
     *
     * The schema already enforces this with nullOnDelete; doing it
     * explicitly here means the intent is visible at the application layer
     * too, and lets the children's sort_order be reset so they land in a
     * sensible order rather than inheriting positions from a level they
     * are no longer on.
     */
    public function delete(MenuItem $item): void
    {
        DB::transaction(function () use ($item) {
            $item->children()->update(['parent_id' => null, 'sort_order' => 0]);

            $item->delete();
        });
    }

    public function toggleVisibility(MenuItem $item): MenuItem
    {
        $item->update(['is_visible' => ! $item->is_visible]);

        return $item->refresh();
    }

    /**
     * Persists a whole tree in one transaction.
     *
     * @param  array<int, array{id: int|string, parent_id?: int|string|null}>  $items
     * @return int rows written
     */
    public function reorder(array $items): int
    {
        return DB::transaction(function () use ($items) {
            $written = 0;

            foreach (array_values($items) as $position => $row) {
                $parentId = $row['parent_id'] ?? null;
                $parentId = ($parentId === '' || $parentId === 'null') ? null : $parentId;

                // Nesting is capped at ONE level: an item may only be
                // parented to something that is itself top-level. The
                // navbar renders parent → children and nothing deeper, so
                // a grandchild would simply vanish from the site.
                if ($parentId !== null && MenuItem::whereKey($parentId)->whereNotNull('parent_id')->exists()) {
                    $parentId = null;
                }

                // An item cannot be its own parent — that would make the
                // tree unrenderable.
                if ($parentId !== null && (int) $parentId === (int) $row['id']) {
                    $parentId = null;
                }

                $written += MenuItem::whereKey($row['id'])->update([
                    'parent_id' => $parentId,
                    'sort_order' => $position,
                ]);
            }

            // A parent that moved under someone else must not leave
            // grandchildren behind; anything whose parent is itself nested
            // gets pulled back to top level.
            MenuItem::whereIn('parent_id', MenuItem::whereNotNull('parent_id')->select('id'))
                ->update(['parent_id' => null]);

            return $written;
        });
    }

    private function nextSortOrder(mixed $parentId): int
    {
        return (int) MenuItem::where('parent_id', $parentId)->max('sort_order') + 1;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        // Every read is null-coalesced: a nullable field the client omits
        // is absent from validated() entirely, and an undefined-key notice
        // is escalated to an ErrorException — and so a 500 — by Laravel's
        // error handler.
        $data['link_type'] = $data['link_type'] ?? 'route';
        $data['target'] = $data['target'] ?? '_self';
        $data['is_visible'] = (bool) ($data['is_visible'] ?? false);
        $data['parent_id'] = ($data['parent_id'] ?? null) ?: null;
        $data['route_name'] = $data['route_name'] ?? null;
        $data['url'] = $data['url'] ?? null;

        // Clear the field the chosen link type does not use, so a stale
        // value cannot resurface if the type is switched back later.
        if ($data['link_type'] !== 'route') {
            $data['route_name'] = null;
        }

        if ($data['link_type'] !== 'url') {
            $data['url'] = null;
        }

        // A dropdown parent has no destination of its own, so a target
        // would be meaningless.
        if ($data['link_type'] === 'none') {
            $data['target'] = '_self';
        }

        // Drop blank locales so `label` stores {"ar": "..."} rather than
        // {"ar": "...", "en": ""} — an empty string is a present value to
        // the translation layer, so it would render as a blank nav item in
        // English instead of falling back to Arabic.
        if (isset($data['label']) && is_array($data['label'])) {
            $data['label'] = array_filter(
                array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data['label']),
                fn ($v) => is_string($v) && $v !== ''
            );
        }

        return $data;
    }
}
