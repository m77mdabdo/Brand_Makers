<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StorePackageEnquiryRequest;
use App\Models\Lead;
use App\Models\Package;
use App\Services\Marketing\AttributionService;
use App\Support\Honeypot;
use Illuminate\Http\RedirectResponse;

/**
 * The "request this package" button on the pricing page.
 *
 * A separate controller from ContactController because the two produce
 * different lead TYPES and carry different data — folding them together
 * would mean a nullable package_id on a form that never has one, and a
 * type derived from whether a field happened to be present.
 */
class PackageEnquiryController extends Controller
{
    public function __construct(private readonly AttributionService $attribution) {}

    public function store(StorePackageEnquiryRequest $request): RedirectResponse
    {
        if (Honeypot::tripped($request)) {
            return $this->submitted();
        }

        $package = Package::findOrFail($request->validated('package_id'));

        Lead::create([
            ...$request->safe()->only(['full_name', 'email', 'phone', 'message']),
            ...$this->attribution->resolve($request),
            'package_id' => $package->getKey(),
            // A distinct type, so the dashboard can tell a pricing
            // enquiry from a general one without inspecting columns.
            'type' => Lead::TYPE_PACKAGE_ENQUIRY,
            'consent_given' => true,
            'consented_at' => now(),
        ]);

        return $this->submitted();
    }

    private function submitted(): RedirectResponse
    {
        return redirect()
            ->to(lroute('packages.index').'#packages')
            ->with('status', __('site.flash.package_enquiry_submitted'));
    }
}
