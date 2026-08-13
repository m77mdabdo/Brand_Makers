<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreContactRequest;
use App\Models\Lead;
use App\Services\Analytics\VisitorSessionService;
use App\Services\Cms\SettingsRepository;
use App\Services\Marketing\AttributionService;
use App\Support\Honeypot;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly AttributionService $attribution,
        private readonly VisitorSessionService $visitorSessions,
    ) {}

    public function show(): View
    {
        return view('public.contact', [
            // Every one of these is null until an admin fills it in, and
            // the view renders nothing in its place — the settings ship
            // empty rather than with placeholder contact details.
            'phone' => $this->settings->string('contact_phone'),
            'email' => $this->settings->string('contact_email'),
            'whatsapp' => $this->settings->string('contact_whatsapp'),
            'address' => $this->settings->translated('contact_address'),
            'socialLinks' => $this->settings->socialLinks(),
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        /*
         * The honeypot is checked BEFORE any write, and a caught bot gets
         * the EXACT same redirect and flash a real submission gets.
         *
         * Returning an error, a different status, or even a
         * measurably-faster response would tell a bot author which field
         * to leave alone next time. Silence is the whole mechanism.
         */
        if (Honeypot::tripped($request)) {
            return $this->submitted();
        }

        $lead = Lead::create([
            ...$request->safe()->only(['full_name', 'email', 'phone', 'message']),
            ...$this->attribution->resolve($request),
            'type' => Lead::TYPE_CONTACT,
            // Proven per lead: the box was `accepted` in validation, and
            // the timestamp records when.
            'consent_given' => true,
            'consented_at' => now(),
        ]);

        /*
         * Close the journey: record the submission as an action on the
         * timeline and attach the session to the lead it produced.
         *
         * Silent when the visitor never consented, when there is no
         * session cookie, or when journey recording is switched off — the
         * gate is checked inside the service, not here, so a form added
         * later cannot record a conversion without it. The enquiry is the
         * point; the journey is a nice-to-have that must never be able to
         * break it.
         */
        $this->visitorSessions->recordConversion($request, 'contact_submitted', $lead);

        return $this->submitted();
    }

    /**
     * lroute(), never route(): an English visitor submitting from
     * /en/contact must land back on /en/contact, not be dropped onto the
     * Arabic site mid-enquiry.
     */
    private function submitted(): RedirectResponse
    {
        return redirect()
            ->to(lroute('contact').'#contact-form')
            ->with('status', __('site.flash.contact_submitted'));
    }
}
