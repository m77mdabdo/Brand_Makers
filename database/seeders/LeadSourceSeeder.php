<?php

namespace Database\Seeders;

use App\Models\LeadSource;
use Illuminate\Database\Seeder;

/**
 * The channel vocabulary attribution resolves against. Real configuration,
 * not sample data — every one of these is a channel the site can actually
 * receive traffic from.
 *
 * All are marked is_system so the CRUD can refuse to delete them:
 * leads.source_platform stores the KEY as a plain string, so removing a
 * source that leads already point at would strand those leads with a value
 * that no longer resolves in reporting.
 */
class LeadSourceSeeder extends Seeder
{
    /**
     * @return array<int, array{key: string, label: string}>
     */
    public static function sources(): array
    {
        return [
            ['key' => 'instagram', 'label' => 'Instagram'],
            ['key' => 'facebook', 'label' => 'Facebook'],
            ['key' => 'tiktok', 'label' => 'TikTok'],
            ['key' => 'snapchat', 'label' => 'Snapchat'],
            ['key' => 'x', 'label' => 'X (Twitter)'],
            ['key' => 'linkedin', 'label' => 'LinkedIn'],
            ['key' => 'behance', 'label' => 'Behance'],
            ['key' => 'google', 'label' => 'Google'],
            ['key' => 'whatsapp', 'label' => 'WhatsApp'],
            ['key' => 'organic', 'label' => 'Organic'],
            ['key' => 'referral', 'label' => 'Referral'],
            ['key' => 'direct', 'label' => 'Direct'],
            ['key' => 'other', 'label' => 'Other'],
        ];
    }

    public function run(): void
    {
        foreach (self::sources() as $sortOrder => $source) {
            $record = LeadSource::updateOrCreate(
                ['key' => $source['key']],
                [
                    'label' => $source['label'],
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]
            );

            // is_system is guarded — assigned, never mass-assigned.
            $record->is_system = true;
            $record->save();
        }
    }
}
