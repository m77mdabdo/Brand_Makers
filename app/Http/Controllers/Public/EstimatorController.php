<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreEstimateRequest;
use App\Models\Lead;
use App\Services\Analytics\VisitorSessionService;
use App\Services\Estimator\EstimatorService;
use App\Services\Marketing\AttributionService;
use App\Support\Honeypot;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The cost estimator: a catalogue to tick, a live total, and an optional
 * "send me this estimate" step that turns the selection into a lead.
 *
 * The live total is computed in the browser so it updates without a round
 * trip, and computed AGAIN here from the database on submission. The two
 * agree because both are summing the same prices, but only this one is
 * trusted — see EstimatorService.
 */
class EstimatorController extends Controller
{
    public function __construct(
        private readonly EstimatorService $estimator,
        private readonly AttributionService $attribution,
        private readonly VisitorSessionService $visitorSessions,
    ) {}

    public function show(): View
    {
        $catalogue = $this->estimator->catalogue();

        return view('public.estimator', [
            'groups' => $catalogue['groups'],
            'packages' => $catalogue['packages'],
            // Decided in the service, not the view: "is there anything
            // priced" is a business rule, and the tests assert it there.
            'hasPricing' => $catalogue['has_pricing'],
        ]);
    }

    public function store(StoreEstimateRequest $request): RedirectResponse
    {
        // Checked before any write, and a caught bot gets the exact same
        // redirect and flash a real submission gets.
        if (Honeypot::tripped($request)) {
            return $this->submitted();
        }

        $quote = $this->estimator->quote(
            $request->validated('package'),
            $request->validated('items') ?? [],
            $request->validated('services') ?? [],
        );

        $lead = Lead::create([
            ...$request->safe()->only(['full_name', 'email', 'phone', 'message']),
            ...$this->attribution->resolve($request),
            'type' => Lead::TYPE_ESTIMATE,
            // The package the estimate was built on, when there was one, so
            // an estimate lead is filterable by package like any other.
            'package_id' => $quote['package']?->getKey(),
            // null when every selected line is priced-on-request: 0.00
            // would be a different and wrong claim.
            'estimate_total' => $quote['total'],
            'estimate_snapshot' => $this->estimator->snapshot($quote),
            'consent_given' => true,
            'consented_at' => now(),
        ]);

        $this->visitorSessions->recordConversion($request, 'estimate_submitted', $lead);

        return $this->submitted();
    }

    /**
     * lroute(), never route(): an English visitor submitting from
     * /en/cost-estimator must land back on /en/cost-estimator.
     */
    private function submitted(): RedirectResponse
    {
        return redirect()
            ->to(lroute('estimator').'#estimate-form')
            ->with('status', __('site.flash.estimate_submitted'));
    }
}
