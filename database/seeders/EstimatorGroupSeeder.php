<?php

namespace Database\Seeders;

use App\Models\EstimatorGroup;
use Illuminate\Database\Seeder;

/**
 * The estimator's selectors, in the order a visitor answers them.
 *
 * ARABIC ONLY; English comes from content:translate-to-english.
 *
 * The base tier is NOT a group here. It is the package selector the
 * estimator already resolves (EstimatorService::quote takes a package slug
 * before it sums any item), and the three tiers deliberately do not exist
 * as items — see EstimatorItemSeeder for why duplicating them would let a
 * visitor be quoted the same tier twice. The public form renders it as the
 * first dropdown, labelled نوع التأسيس, so it reads as one selector among
 * the others even though it is fed from a different table.
 *
 * Idempotent on `key`, and neither is_active nor sort_order is re-applied
 * to an existing row: hiding or reordering a group is an admin decision.
 */
class EstimatorGroupSeeder extends Seeder
{
    /**
     * The groups the branding catalogue used, replaced by the four below.
     *
     * These never existed as rows — their labels lived in the lang file —
     * so there is nothing to delete. Listed for the reader who goes
     * looking for them after finding the keys in an old estimator_items
     * row: identity, strategy, print, digital, content, and later
     * licensing, banking, compliance, advisory.
     */
    public function run(): void
    {
        foreach ($this->groups() as $sortOrder => $group) {
            $existing = EstimatorGroup::where('key', $group['key'])->first();

            if ($existing) {
                $existing->update(['label' => $group['label'], 'selection' => $group['selection']]);

                continue;
            }

            EstimatorGroup::create($group + ['sort_order' => $sortOrder, 'is_active' => true]);
        }
    }

    /**
     * @return array<int, array{key: string, selection: string, label: array<string, string>}>
     */
    private function groups(): array
    {
        return [
            [
                'key' => 'services',
                'selection' => EstimatorGroup::SELECTION_MULTI,
                'label' => ['ar' => 'الخدمات'],
            ],
            [
                // Single: acquiring a company abroad is one arrangement,
                // not a basket. A checkbox list here would let a visitor
                // total two acquisitions of the same entity.
                'key' => 'foreign-acquisition',
                'selection' => EstimatorGroup::SELECTION_SINGLE,
                'label' => ['ar' => 'الاستحواذ على شركة خارجية'],
            ],
            [
                'key' => 'other-services',
                'selection' => EstimatorGroup::SELECTION_MULTI,
                'label' => ['ar' => 'خدمات أخرى'],
            ],
        ];
    }
}
