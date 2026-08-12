<?php

namespace App\Services\Cms;

use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Read access to the settings table, loaded ONCE per request.
 *
 * Registered as a singleton (see AppServiceProvider), so however many
 * callers ask for a value — the layout composer, the footer, the SEO tags
 * — the table is read at most once. Without that, the public layout alone
 * would issue half a dozen identical queries on every page render.
 */
class SettingsRepository
{
    /** @var Collection<string, mixed>|null */
    private ?Collection $values = null;

    /**
     * Raw stored value, whatever its shape (scalar, bool, int, or a
     * per-locale map).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()->get($key, $default);
    }

    /**
     * A plain string, or null when unset or blank.
     *
     * Blank is deliberately folded into null: the settings are seeded
     * empty rather than with placeholder text, and the layout hides what
     * is not configured. A caller checking `!== null` should not have to
     * also check for an empty string.
     */
    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key);

        if (is_array($value)) {
            $value = $this->pickLocale($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        return trim($value);
    }

    /**
     * A per-locale value in the CURRENT locale, falling back to the app
     * fallback locale — the same rule spatie/laravel-translatable applies
     * to model columns, so settings and content behave alike.
     */
    public function translated(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key);

        if (is_string($value)) {
            return trim($value) === '' ? $default : trim($value);
        }

        if (! is_array($value)) {
            return $default;
        }

        return $this->pickLocale($value) ?? $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return is_bool($value) ? $value : $default;
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return is_int($value) ? $value : $default;
    }

    /**
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        $links = [];

        foreach (['instagram', 'behance', 'linkedin', 'x', 'tiktok', 'facebook', 'youtube'] as $platform) {
            $url = $this->string('social_'.$platform);

            if ($url !== null) {
                $links[$platform] = $url;
            }
        }

        return $links;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function pickLocale(array $value): ?string
    {
        $candidate = $value[app()->getLocale()]
            ?? $value[config('app.fallback_locale')]
            ?? null;

        return is_string($candidate) && trim($candidate) !== '' ? trim($candidate) : null;
    }

    /**
     * @return Collection<string, mixed>
     */
    private function all(): Collection
    {
        return $this->values ??= Setting::query()->pluck('value', 'key');
    }
}
