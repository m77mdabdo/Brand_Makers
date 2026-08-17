<?php

namespace App\Services\Cms;

use App\Models\EstimatorGroup;
use Illuminate\Support\Str;

/**
 * Writes to the estimator's selectors.
 *
 * ARBITRARY-KEY SAFETY, in the same three independent layers the rest of
 * this namespace uses: the form requests derive rules per field so
 * validated() drops anything unlisted, sanitize() intersects with
 * WRITABLE, and EstimatorGroup::$fillable guards the mass assignment.
 *
 * `key` is generated from the Arabic label when the admin leaves it blank
 * — the same courtesy the slugged resources give.
 */
class EstimatorGroupService extends ContentResourceService
{
    /** @var list<string> */
    private const WRITABLE = ['key', 'label', 'selection', 'sort_order', 'is_active'];

    protected function model(): string
    {
        return EstimatorGroup::class;
    }

    /**
     * The label is a selector heading, never rich text — false reduces it
     * to plain text on write, so no markup reaches the public form.
     */
    protected function translatableFields(): array
    {
        return ['label' => false];
    }

    protected function supportsSeo(): bool
    {
        return false;
    }

    protected function booleanFields(): array
    {
        return ['is_active'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitize(array $data): array
    {
        $data = parent::sanitize($data);

        $data = array_intersect_key($data, array_flip(self::WRITABLE));

        /*
         * THE KEY IS SET ON CREATE AND NEVER CHANGED, for the same reason
         * an item's key is not: estimator_items.group points at it by
         * value. Renaming it here would orphan every item in the group
         * from its heading in one write.
         *
         * UpdateEstimatorGroupRequest therefore has no `key` rule, so
         * validated() drops it and this branch never runs on an update.
         */
        if (array_key_exists('key', $data)) {
            $key = is_string($data['key']) ? trim($data['key']) : '';

            if ($key === '') {
                $labels = $data['label'] ?? [];
                $key = Str::slug((string) (is_array($labels) ? ($labels['ar'] ?? $labels['en'] ?? '') : $labels));
            }

            $data['key'] = $key !== '' ? $key : 'group-'.Str::random(6);
        }

        return $data;
    }
}
