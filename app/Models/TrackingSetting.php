<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingSetting extends Model
{
    use HasFactory;

    /**
     * key => the format that key's value must match.
     *
     * WHY THIS IS NOT COSMETIC. These values end up interpolated into
     * inline <script> blocks. Blade's {{ }} escaping does hold there, but
     * that leaves the whole boundary resting on HTML escaping behaving
     * correctly inside a JavaScript context, which is not what it is for.
     * Pinning each ID to its platform's published shape means nothing that
     * is not an ID can reach the page in the first place.
     *
     * Patterns are deliberately a little looser than the current published
     * formats (length ranges rather than exact lengths): a platform
     * lengthening its IDs should not lock an admin out of the field. They
     * are still narrow enough that no character with meaning in HTML or
     * JavaScript is accepted.
     *
     * @var array<string, string>
     */
    public const FORMATS = [
        // Numeric, currently 15-16 digits.
        'meta_pixel_id' => '/^[0-9]{8,25}$/',
        // GTM-XXXXXXX
        'gtm_container_id' => '/^GTM-[A-Z0-9]{4,15}$/i',
        // G-XXXXXXXXXX
        'ga4_measurement_id' => '/^G-[A-Z0-9]{6,20}$/i',
        // AW-XXXXXXXXX
        'google_ads_conversion_id' => '/^AW-[0-9]{6,20}$/i',
        // An opaque base64url-ish token, e.g. AbC-dEfGhIjKlMnOp.
        'google_ads_conversion_label' => '/^[A-Za-z0-9_-]{4,60}$/',
        // Alphanumeric, currently 20 characters.
        'tiktok_pixel_id' => '/^[A-Z0-9]{10,40}$/i',
        // Snap Pixel IDs are UUIDs.
        'snap_pixel_id' => '/^[0-9a-f-]{20,40}$/i',
    ];

    protected $fillable = [
        'key',
        'value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function matchesItsFormat(): bool
    {
        $pattern = self::FORMATS[$this->key] ?? null;

        if ($pattern === null || $this->value === null || $this->value === '') {
            return false;
        }

        return (bool) preg_match($pattern, $this->value);
    }
}
