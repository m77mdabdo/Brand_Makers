<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Toggles the CURRENT user's dashboard language.
     *
     * Takes no user id and no target locale from the request — it flips
     * auth()->user()'s own preference between the two supported values.
     * Accepting either would let one admin change another's settings, or
     * let a crafted payload store a locale the app has no translations
     * for (which would render the whole dashboard as raw translation
     * keys).
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->update(['locale' => $user->locale === 'en' ? 'ar' : 'en']);

        return back();
    }
}
