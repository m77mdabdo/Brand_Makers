<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Package;
use App\Models\Service;
use Illuminate\View\View;

/**
 * The dashboard landing screen.
 *
 * Counts only, for now — the managers for each of these are separate
 * build steps. Every figure is a real COUNT(*) against an indexed column;
 * nothing here is illustrative, so an empty install shows zeros rather
 * than sample numbers.
 */
class DashboardHomeController extends Controller
{
    public function index(): View
    {
        return view('dashboard.home', [
            'stats' => [
                'services' => Service::count(),
                'packages' => Package::count(),
                'articles' => Article::count(),
                'leads' => Lead::count(),
                'bookings' => Booking::count(),
                'orders' => Order::count(),
            ],
            'unhandledLeads' => Lead::unhandled()->count(),
        ]);
    }
}
