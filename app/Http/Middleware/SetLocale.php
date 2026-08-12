<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the app locale from the matched ROUTE NAME, not from the URL, a
 * session or a cookie.
 *
 * Every public route is registered twice (see routes/web.php): canonical
 * names with no prefix for Arabic, "{name}.en" names under an "en" prefix
 * for English. The route name is the one unambiguous signal for which
 * variant matched — a route named "services.show.en" is definitionally the
 * English one regardless of what its parameters happen to contain, whereas
 * sniffing the path would misread a service whose slug legitimately starts
 * with "en".
 *
 * Name-based also means the language toggle and the hreflang tags are
 * driven by the same fact the router already decided, so a page can never
 * render in one locale while advertising itself as the other.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = str_ends_with((string) Route::currentRouteName(), '.en') ? 'en' : 'ar';

        app()->setLocale($locale);

        return $next($request);
    }
}
