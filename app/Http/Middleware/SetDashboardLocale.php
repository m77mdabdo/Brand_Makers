<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the dashboard's locale from the authenticated STAFF member's
 * own `locale` column.
 *
 * Completely independent of the public site's locale, which SetLocale
 * derives per-visitor from the matched route name. The two must not share
 * a mechanism: an admin who prefers the dashboard in English should not
 * thereby be browsing the Arabic public site in English, and a visitor's
 * URL should never change what an admin sees in the back office.
 *
 * Applied only to auth-gated routes. There is no user record to read a
 * preference from on the login or forgot-password screens, so those stay
 * on the app default.
 *
 * Carbon::setLocale as well as app()->setLocale, because the dashboard
 * renders relative dates ("3 days ago") and those come from Carbon's own
 * translations rather than the app's.
 */
class SetDashboardLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? config('app.fallback_locale');

        // A stored value outside the supported set would make __() fall
        // through to raw keys across the whole dashboard.
        if (! in_array($locale, config('app.supported_locales'), true)) {
            $locale = config('app.fallback_locale');
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
