<?php

namespace App\Services\Cms;

use App\Models\EstimatorItem;
use Illuminate\Support\Str;

/**
 * Writes to the estimator catalogue.
 *
 * ARBITRARY-KEY SAFETY, in three independent layers. Any one of them would
 * do today; all three together mean a crafted payload still cannot write a
 * column nobody intended, even after somebody later loosens one of them —
 * which is exactly the change that gets made without noticing what it was
 * protecting.
 *
 *   1. StoreEstimatorItemRequest / UpdateEstimatorItemRequest derive rules
 *      per field, so validated() drops anything unlisted.
 *   2. sanitize() below intersects the payload with WRITABLE, so a key
 *      that survived validation is still never read.
 *   3. EstimatorItem::$fillable guards the mass assignment itself.
 *
 * `key` is generated from the label when the admin leaves it blank, the
 * same courtesy the slugged resources give — an admin pricing a catalogue
 * should not have to invent stable identifiers by hand.
 */
class EstimatorItemService extends ContentResourceService
{
    /**
     * Every column this service may write, and nothing else.
     *
     * `currency` is absent deliberately: the catalogue is single-currency
     * and the column carries a default, so exposing it in the editor would
     * offer an admin a way to create a line that cannot be summed with the
     * others (see EstimatorService, which refuses to total a mixed-currency
     * selection).
     *
     * @var list<string>
     */
    private const WRITABLE = ['key', 'label', 'group', 'price', 'is_active', 'sort_order'];

    protected function model(): string
    {
        return EstimatorItem::class;
    }

    /**
     * The label is a checkbox caption, never rich text — false here means
     * it is reduced to plain text on write, so no markup can reach the
     * estimator form.
     */
    protected function translatableFields(): array
    {
        return ['label' => false];
    }

    /** Estimator lines are not addressable pages. */
    protected function supportsSeo(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitize(array $data): array
    {
        $data = parent::sanitize($data);

        $data = array_intersect_key($data, array_flip(self::WRITABLE));

        if (array_key_exists('price', $data)) {
            // Stored as a decimal string rather than a float: the column is
            // decimal(12,2) and every consumer sums it as minor units, so
            // there is no point letting a float representation in at all.
            $data['price'] = number_format((float) $data['price'], 2, '.', '');
        }

        /*
         * THE KEY IS SET ON CREATE AND NEVER CHANGED.
         *
         * EstimatorItemSeeder matches on it to stay idempotent, and every
         * stored estimate snapshot records it so a quoted line can be
         * traced back to its catalogue row. Renaming it would make the
         * next re-seed create a duplicate item and orphan the history.
         *
         * UpdateEstimatorItemRequest therefore has no `key` rule at all,
         * so validated() drops it and this branch never runs on an update
         * — a payload that smuggles one in changes nothing.
         */
        if (array_key_exists('key', $data)) {
            $data['key'] = $this->resolveKey($data);
        }

        return $data;
    }

    /**
     * The submitted key, or one derived from the label when it is blank.
     *
     * Prefers the English label because `key` is an internal identifier
     * read by developers in seeders and snapshots, and a transliterated
     * Arabic slug is a poor thing to have to type. Falls back to the
     * Arabic label — which is always present — and finally to a generic
     * stem, so this can never return an empty string into a unique column.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveKey(array $data): string
    {
        $submitted = is_string($data['key'] ?? null) ? trim($data['key']) : '';

        if ($submitted !== '') {
            return Str::slug($submitted) ?: $submitted;
        }

        $label = is_array($data['label'] ?? null) ? $data['label'] : [];
        $stem = Str::slug((string) ($label['en'] ?? '')) ?: Str::slug((string) ($label['ar'] ?? ''));

        return $this->unique($stem !== '' ? $stem : 'estimator-item');
    }

    /**
     * Appends -2, -3… until the key is free.
     *
     * Only used for GENERATED keys. A key an admin typed is left exactly
     * as typed and allowed to collide, so the unique rule in the Form
     * Request reports it rather than this silently renaming their input.
     */
    private function unique(string $stem): string
    {
        $key = $stem;
        $suffix = 2;

        while (EstimatorItem::where('key', $key)->exists()) {
            $key = $stem.'-'.$suffix++;
        }

        return $key;
    }
}
