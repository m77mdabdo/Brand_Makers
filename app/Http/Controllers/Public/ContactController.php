<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Cms\SettingsRepository;
use Illuminate\View\View;

/**
 * The contact page — DISPLAY ONLY for now.
 *
 * The submission form and its lead capture are the next build step, so
 * there is deliberately no store() here yet: a form that posted nowhere
 * would look functional and silently lose enquiries.
 */
class ContactController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

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
}
