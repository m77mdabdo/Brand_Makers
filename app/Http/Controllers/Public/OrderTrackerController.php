<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\TrackOrderRequest;
use App\Models\Order;
use App\Services\Orders\OrderLookupService;
use App\Services\Orders\OrderTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The client portal: one order, its own.
 *
 * THERE IS NO ORDER IDENTIFIER IN ANY URL. Not a reference, not an id, not
 * a signed token. The verified order lives in the visitor's SESSION and
 * the page is always /track — so "reach another order by editing the URL"
 * is not a thing that can be attempted, rather than a thing that is
 * checked for. That is also why the lookup is a POST followed by a
 * redirect: the credentials never end up in browser history, in a referrer
 * header, or in a bookmark somebody shares.
 */
class OrderTrackerController extends Controller
{
    /**
     * Where the verified order's id is held between requests.
     *
     * The id, not the reference or the code: a session value is
     * server-side and per-client, so it needs to be no more than a pointer
     * — and keeping the code out of the session means it exists nowhere
     * after the request that verified it.
     */
    public const SESSION_KEY = 'order_tracker.order_id';

    public function __construct(
        private readonly OrderLookupService $lookup,
        private readonly OrderTimeline $timeline,
    ) {}

    public function show(Request $request): View
    {
        $order = $this->sessionOrder($request);

        return view('public.order-tracker', [
            'order' => $order,
            // Built here rather than in the view: the stage list is
            // business logic, and the view should not be deciding what
            // "in review" means.
            'timeline' => $order ? $this->timeline->forOrder($order) : [],
        ]);
    }

    public function lookup(TrackOrderRequest $request): RedirectResponse
    {
        $order = $this->lookup->find(
            $request->validated('reference'),
            $request->validated('tracking_code'),
        );

        if ($order === null) {
            /*
             * ONE MESSAGE FOR EVERY FAILURE. Wrong code, wrong reference,
             * unknown reference, archived order — all land here. Anything
             * more specific would turn the portal into an oracle for which
             * references exist.
             *
             * The reference is returned to the field so a client who
             * mistyped the code does not retype both; the code is not,
             * because a credential should not sit in a repopulated form.
             */
            return redirect()
                ->to(lroute('orders.track').'#track-form')
                ->withInput($request->only('reference'))
                ->with('error', __('site.tracker.not_found'));
        }

        // Regenerated on a successful lookup: this is a privilege change,
        // and it costs nothing to close session fixation here.
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $order->getKey());

        return redirect()->to(lroute('orders.track').'#order-status');
    }

    /**
     * Forgets the order — the "check another order" action, and the only
     * way to leave the portal short of the session expiring.
     */
    public function forget(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->to(lroute('orders.track'));
    }

    /**
     * The order held in the session, re-read on every request.
     *
     * Re-read rather than cached in the session, so a status change made
     * in the dashboard is visible to the client on their next refresh —
     * and so an order archived after they looked it up disappears from
     * their portal rather than lingering as a stale copy.
     */
    private function sessionOrder(Request $request): ?Order
    {
        $id = $request->session()->get(self::SESSION_KEY);

        if (! is_int($id) && ! ctype_digit((string) $id)) {
            return null;
        }

        return Order::query()
            ->with(['package', 'events'])
            ->find((int) $id);
    }
}
