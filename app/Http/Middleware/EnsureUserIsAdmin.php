<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The dashboard gate.
 *
 * Two DIFFERENT outcomes, on purpose:
 *
 *   guest            → redirected to login. They may well be an admin who
 *                      simply is not signed in, and redirect()->guest()
 *                      records the intended URL so they land where they
 *                      were going.
 *   signed-in, not   → 403. Redirecting them to login would be a lie —
 *   an admin           they ARE authenticated — and would loop, since the
 *                      login route sends an authenticated user straight
 *                      back to the dashboard.
 *
 * A 403 also tells the truth to the person seeing it without confirming
 * anything useful to an attacker: the response is identical whether or
 * not the URL they guessed corresponds to a real dashboard screen.
 *
 * `is_admin` is cast-only and guarded out of $fillable (see User), so the
 * flag this reads can never have been set by a request payload.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        abort_unless($request->user()->is_admin, 403);

        return $next($request);
    }
}
