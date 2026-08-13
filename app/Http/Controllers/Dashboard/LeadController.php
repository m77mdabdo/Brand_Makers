<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $leads = Lead::query()
            // Eager-loaded so the index stays a handful of queries rather
            // than one per row for the service/package/campaign columns.
            ->with(['requestedService', 'package', 'campaign'])
            /*
             * Every filter is conditional, so an absent parameter means
             * "no filter" rather than "match empty".
             *
             * `type` is checked against the known vocabulary instead of
             * being passed through: an arbitrary value would return an
             * empty list that reads as "no leads" rather than "bad
             * filter".
             */
            ->when(
                in_array($request->query('type'), Lead::TYPES, true),
                fn ($query) => $query->where('type', $request->query('type'))
            )
            ->when($request->filled('source_platform'), fn ($query) => $query->where('source_platform', $request->query('source_platform')))
            ->when($request->filled('requested_service_id'), fn ($query) => $query->where('requested_service_id', $request->query('requested_service_id')))
            ->when($request->filled('package_id'), fn ($query) => $query->where('package_id', $request->query('package_id')))
            // whereDate, so an inclusive "to" date covers the whole day
            // rather than stopping at midnight.
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->query('date_to')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.leads.index', [
            'leads' => $leads,
            'services' => Service::orderBy('sort_order')->get(),
            'packages' => Package::orderBy('sort_order')->get(),
            // Only channels that actually appear in the data — offering
            // every configured source would list filters returning
            // nothing.
            'sourcePlatforms' => Lead::query()
                ->whereNotNull('source_platform')
                ->distinct()
                ->orderBy('source_platform')
                ->pluck('source_platform'),
            'filters' => $request->only([
                'type', 'source_platform', 'requested_service_id', 'package_id', 'date_from', 'date_to',
            ]),
        ]);
    }

    public function show(Lead $lead): View
    {
        return view('dashboard.leads.show', [
            'lead' => $lead->load(['requestedService', 'package', 'campaign']),
        ]);
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        // Lead soft-deletes, so this ARCHIVES rather than destroys: an
        // enquiry is a historical record, and the conversion events and
        // visitor journeys attached to it keep resolving withTrashed().
        $lead->delete();

        return redirect()
            ->route('dashboard.leads.index')
            ->with('status', __('dashboard.flash.deleted'));
    }
}
