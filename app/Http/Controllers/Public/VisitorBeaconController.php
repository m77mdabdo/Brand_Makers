<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Analytics\VisitorSessionService;
use App\Services\Analytics\VisitorTrackingGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * Receives the client's time-on-page report.
 *
 * The only endpoint in this feature that accepts client-supplied numbers,
 * which is why everything it takes is validated and clamped rather than
 * trusted. The session it writes to is identified by the httpOnly cookie,
 * never by anything in the payload — so a caller cannot report time into
 * somebody else's journey even knowing their uid.
 *
 * RETURNS 204 IN EVERY CASE, including refusal, and with no body. A beacon
 * has nobody listening to its response: the page that issued it is usually
 * already gone. Telling an unknown caller whether their session exists, or
 * whether consent was granted, would be a disclosure with no reader.
 */
class VisitorBeaconController extends Controller
{
    /**
     * Matches the client cap in resources/js/visitor-session.js. Duplicated
     * on purpose — the client value is a courtesy, this one is the rule.
     */
    private const MAX_DURATION_MS = 30 * 60 * 1000;

    public function __construct(
        private readonly VisitorTrackingGate $gate,
        private readonly VisitorSessionService $sessions,
    ) {}

    public function __invoke(Request $request): Response
    {
        if (! $this->gate->allows($request)) {
            return response()->noContent();
        }

        $uid = $request->cookie(VisitorSessionService::COOKIE);

        if (! $this->sessions->isValidUid($uid)) {
            return response()->noContent();
        }

        /*
         * Validator::make rather than $request->validate().
         *
         * The latter throws ValidationException, which for a non-JSON
         * request becomes a 302 back to the previous page. sendBeacon has
         * nobody to redirect — so a failure here has to be the same silent
         * 204 as every other outcome, not a redirect the browser will
         * fetch and discard.
         */
        $validator = Validator::make($request->all(), [
            'url' => ['required', 'string', 'max:1000'],
            'duration_ms' => ['required', 'integer', 'min:0', 'max:'.self::MAX_DURATION_MS],
        ]);

        if ($validator->fails()) {
            return response()->noContent();
        }

        $validated = $validator->validated();

        $this->sessions->recordDuration($uid, $validated['url'], (int) $validated['duration_ms']);

        return response()->noContent();
    }
}
