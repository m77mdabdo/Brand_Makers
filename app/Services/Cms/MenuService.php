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
     * THE SUBMITTED sort_order IS WHAT DECIDES THE ORDER.
     *
     * This used to ignore it entirely and write $position — the index of
     * the row in the posted array. The form posts `items[<id>][...]`
     * keyed by id, so PHP hands them back in the order they were
     * RENDERED, which is the order already stored. Every save therefore
     * rewrote the existing arrangement onto itself: the admin typed new
     * numbers, saw "تم حفظ الترتيب", and nothing moved. It was a no-op
     * that reported success.
     *
     * So the rows are sorted by the number the admin typed, and only then
     * numbered 0..n. Sorting rather than storing the raw value is what
     * keeps sort_order contiguous and gap-free — the admin can type 10,
     * 20, 30 to leave room and still get 0, 1, 2 stored — and it is what
     * lets a value repeated across two rows resolve deterministically
     * rather than leaving two items fighting for one slot.
     *
     * Numbering is PER PARENT: children are ordered within their own
     * parent, so a child's 0 does not compete with a top-level item's 0.
     *
     * @param  array<int|string, array{id: int|string, parent_id?: int|string|null, sort_order?: int|string|null}>  $items
     * @return int rows written
     */
    public function reorder(array $items): int
    {
        return DB::transaction(function () use ($items) {
            $written = 0;

            foreach ($this->sequence($items) as $row) {
                $written += MenuItem::whereKey($row['id'])->update([
                    'parent_id' => $row['parent_id'],
                    'sort_order' => $row['sort_order'],
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

    /**
     * Turns the posted rows into what should actually be written: a
     * resolved parent, and a contiguous position within that parent.
     *
     * Order of operations matters. The parent is normalised FIRST,
     * because an item whose illegal parent is reset to null joins the
     * top-level group and has to be numbered against those siblings, not
     * against the children it was trying to join.
     *
     * The sort is stable on the submitted order, so two rows carrying the
     * same number keep the arrangement the admin is looking at rather
     * than swapping unpredictably on each save.
     *
     * @param  array<int|string, array{id: int|string, parent_id?: int|string|null, sort_order?: int|string|null}>  $items
     * @return list<array{id: int, parent_id: int|null, sort_order: int}>
     */
    private function sequence(array $items): array
    {
        $rows = [];

        foreach (array_values($items) as $submittedAt => $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'parent_id' => $this->resolveParent($row),
                // The number the admin typed. Absent or non-numeric sorts
                // to the front rather than throwing — a menu save must not
                // 500 on a field somebody cleared.
                'requested' => is_numeric($row['sort_order'] ?? null) ? (int) $row['sort_order'] : 0,
                'submitted_at' => $submittedAt,
            ];
        }

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['parent_id'] ?? 0][] = $row;
        }

        $sequenced = [];

        foreach ($grouped as $siblings) {
            usort($siblings, fn (array $a, array $b) => [$a['requested'], $a['submitted_at']] <=> [$b['requested'], $b['submitted_at']]);

            foreach (array_values($siblings) as $position => $row) {
                $sequenced[] = [
                    'id' => $row['id'],
                    'parent_id' => $row['parent_id'],
                    'sort_order' => $position,
                ];
            }
        }

        return $sequenced;
    }

    /**
     * @param  array{id: int|string, parent_id?: int|string|null}  $row
     */
    private function resolveParent(array $row): ?int
    {
        $parentId = $row['parent_id'] ?? null;

        if ($parentId === '' || $parentId === null || $parentId === 'null') {
            return null;
        }

        // Nesting is capped at ONE level: an item may only be parented to
        // something that is itself top-level. The navbar renders parent →
        // children and nothing deeper, so a grandchild would simply vanish
        // from the site.
        if (MenuItem::whereKey($parentId)->whereNotNull('parent_id')->exists()) {
            return null;
        }

        // An item cannot be its own parent — that would make the tree
        // unrenderable.
        if ((int) $parentId === (int) $row['id']) {
            return null;
        }

        return (int) $parentId;
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
