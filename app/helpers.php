<?php

use Illuminate\Support\Facades\Route;

if (! function_exists('lroute')) {
    /**
     * Locale-aware route().
     *
     * Every public route is registered TWICE (see routes/web.php) —
     * canonical names for Arabic, "{name}.en" for English. This resolves
     * to whichever variant matches the CURRENT locale, so Blade views
     * never need an if/else around a plain route() call.
     *
     * Falls straight through to the given name unchanged when no ".en"
     * variant is registered (dashboard and auth routes are single-locale),
     * which makes it always safe to use in a shared layout.
     */
    function lroute(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        if (app()->getLocale() === 'en' && ! str_ends_with($name, '.en') && Route::has("{$name}.en")) {
            return route("{$name}.en", $parameters, $absolute);
        }

        return route($name, $parameters, $absolute);
    }
}

if (! function_exists('current_route_base_name')) {
    /**
     * The current route's name with any ".en" suffix stripped — so
     * "services.show.en" and "services.show" both return "services.show".
     *
     * This is what lets navigation compare "am I on this page?" without
     * caring which locale the visitor is reading.
     */
    function current_route_base_name(): ?string
    {
        $name = Route::currentRouteName();

        if (! $name) {
            return null;
        }

        return str_ends_with($name, '.en') ? substr($name, 0, -3) : $name;
    }
}

if (! function_exists('route_in_locale')) {
    /**
     * The URL for the CURRENT page in the given locale, preserving every
     * route parameter (a service slug, an article slug) — the basis for
     * both the navbar language toggle and the <head> hreflang tags.
     *
     * Returns null when the current request is not on a dual-registered
     * public route (dashboard and auth pages, which have no English
     * variant and therefore no hreflang tags either).
     */
    function route_in_locale(string $locale): ?string
    {
        $currentRoute = request()->route();
        $baseName = current_route_base_name();

        if (! $currentRoute || ! $baseName) {
            return null;
        }

        $name = $locale === 'en' ? "{$baseName}.en" : $baseName;

        if (! Route::has($name)) {
            return null;
        }

        return route($name, $currentRoute->parameters());
    }
}
