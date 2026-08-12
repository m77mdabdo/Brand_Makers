<?php

namespace App\Services\Cms;

use App\Models\TrackingSetting;
use Illuminate\Support\Facades\DB;

/**
 * Writes iterate TrackingSetting::FORMATS, never the request payload.
 *
 * Same defence as the settings editor, and it matters more here: these
 * values are interpolated into inline <script> blocks, so an arbitrary
 * key reaching this table would put attacker-controlled text into a
 * script context on every page of the public site.
 */
class TrackingSettingService
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach (array_keys(TrackingSetting::FORMATS) as $key) {
                $value = $values[$key] ?? null;
                $value = is_string($value) ? trim($value) : null;
                $value = $value === '' ? null : $value;

                TrackingSetting::updateOrCreate(['key' => $key], [
                    'value' => $value,
                    // A pixel with no ID cannot fire, so an emptied field
                    // is deactivated rather than left switched on
                    // pointing at nothing.
                    'is_active' => $value !== null && (bool) ($values['active'][$key] ?? false),
                ]);
            }
        });
    }
}
