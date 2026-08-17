<?php

use App\Http\Controllers\Dashboard\ArticleController;
use App\Http\Controllers\Dashboard\BookingAvailabilityController;
use App\Http\Controllers\Dashboard\BookingController;
use App\Http\Controllers\Dashboard\CampaignController;
use App\Http\Controllers\Dashboard\CommentController;
use App\Http\Controllers\Dashboard\ContactMessageController;
use App\Http\Controllers\Dashboard\CountryController;
use App\Http\Controllers\Dashboard\DashboardHomeController;
use App\Http\Controllers\Dashboard\EstimatorGroupController;
use App\Http\Controllers\Dashboard\EstimatorItemController;
use App\Http\Controllers\Dashboard\FaqController;
use App\Http\Controllers\Dashboard\LeadController;
use App\Http\Controllers\Dashboard\LocaleController;
use App\Http\Controllers\Dashboard\MediaController;
use App\Http\Controllers\Dashboard\MenuItemController;
use App\Http\Controllers\Dashboard\OrderController;
use App\Http\Controllers\Dashboard\PackageController;
use App\Http\Controllers\Dashboard\PageController;
use App\Http\Controllers\Dashboard\PageSectionController;
use App\Http\Controllers\Dashboard\ServiceController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\TestimonialController;
use App\Http\Controllers\Dashboard\TrackingSettingController;
use App\Http\Controllers\Dashboard\VisitorSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard — staff only
|--------------------------------------------------------------------------
| Single-locale routes (no ".en" variants): the dashboard's language comes
| from the signed-in admin's own `locale` column via 'dashboardlocale',
| not from the URL. See SetDashboardLocale for why the two mechanisms are
| deliberately separate.
|
| 'throttle:dashboard-writes' applies to the whole group but counts only
| UNSAFE methods — the limiter returns Limit::none() for GET/HEAD (see
| AppServiceProvider::configureRateLimiting), so browsing the dashboard is
| unrationed while every write is capped per admin. Without it a stolen
| session had no ceiling at all.
|
| Every route here is inside this one group. RouteSweepTest asserts that:
| a dashboard screen registered outside it would be publicly reachable and
| nothing else would notice.
*/
Route::prefix('dashboard')
    ->middleware(['auth', 'admin', 'dashboardlocale', 'throttle:dashboard-writes'])
    ->group(function () {
        Route::get('/', [DashboardHomeController::class, 'index'])->name('dashboard');

        Route::name('dashboard.')->group(function () {
            // --- content ------------------------------------------------
            /*
             * `:id` on the parameter, overriding each model's
             * getRouteKeyName().
             *
             * The public site binds these by SLUG, which is right for a
             * URL a visitor sees and a crawler indexes. The dashboard must
             * not: an admin who edits a record's slug would otherwise 404
             * their own bookmarked edit URL, and a slug derived from
             * Arabic is a poor thing to have to read out of an address
             * bar. Ids are stable and are what the resource controller's
             * model binding wants anyway.
             */
            /*
             * Services, Countries and Articles are declared explicitly
             * rather than with Route::resource, purely so the binding key
             * can be pinned to `:id`.
             *
             * Those models return 'slug' from getRouteKeyName(), which is
             * right for the PUBLIC site — a URL a visitor sees and a
             * crawler indexes. It is wrong here: an admin who edits a
             * record's slug would 404 their own bookmarked edit URL, and a
             * slug transliterated from Arabic is a poor thing to have to
             * read out of an address bar. Route::resource has no way to
             * override the key (parameters() renames the placeholder, it
             * does not rebind it), so these six lines are the cost of
             * getting it right.
             */
            $idBound = function (string $name, string $controller, string $param): void {
                Route::get($name, [$controller, 'index'])->name("{$name}.index");
                Route::get("{$name}/create", [$controller, 'create'])->name("{$name}.create");
                Route::post($name, [$controller, 'store'])->name("{$name}.store");
                Route::get("{$name}/{{$param}:id}/edit", [$controller, 'edit'])->name("{$name}.edit");
                Route::match(['put', 'patch'], "{$name}/{{$param}:id}", [$controller, 'update'])->name("{$name}.update");
                Route::delete("{$name}/{{$param}:id}", [$controller, 'destroy'])->name("{$name}.destroy");
            };

            $idBound('services', ServiceController::class, 'service');
            // Packages bind by slug publicly and by id here, like the
            // other slugged resources — an admin who edits a slug must not
            // 404 their own bookmarked edit URL.
            $idBound('packages', PackageController::class, 'package');
            $idBound('countries', CountryController::class, 'country');
            $idBound('articles', ArticleController::class, 'article');

            /*
             * The estimator's price list.
             *
             * Route::resource, not $idBound: EstimatorItem has no slug, so
             * its default binding is already the id. `except('show')` —
             * there is nothing to show that the edit form does not.
             */
            Route::resource('estimator-items', EstimatorItemController::class)
                ->except('show')
                ->parameters(['estimator-items' => 'estimatorItem']);

            // The selectors those items are grouped into. Same shape and
            // the same reasoning: no slug, so the default binding is the
            // id, and nothing to show beyond the edit form.
            Route::resource('estimator-groups', EstimatorGroupController::class)
                ->except('show')
                ->parameters(['estimator-groups' => 'estimatorGroup']);

            // Faqs and Testimonials have no slug, so their default binding
            // is already the id.
            Route::resource('faqs', FaqController::class)->except('show');
            Route::resource('testimonials', TestimonialController::class)->except('show');

            /*
             * Pages are seeded and referenced by NAME from the footer, the
             * navigation whitelist and the sitemap — so no create and no
             * destroy. A page created here would have no route to live at;
             * deleting one would 500 every view that links to it.
             */
            Route::get('pages', [PageController::class, 'index'])->name('pages.index');
            Route::get('pages/{page:id}/edit', [PageController::class, 'edit'])->name('pages.edit');
            Route::match(['put', 'patch'], 'pages/{page:id}', [PageController::class, 'update'])->name('pages.update');
            Route::get('pages/{page:id}/sections/create', [PageSectionController::class, 'create'])->name('pages.sections.create');
            Route::post('pages/{page:id}/sections', [PageSectionController::class, 'store'])->name('pages.sections.store');
            Route::get('pages/{page:id}/sections/{section:id}/edit', [PageSectionController::class, 'edit'])->name('pages.sections.edit');
            Route::match(['put', 'patch'], 'pages/{page:id}/sections/{section:id}', [PageSectionController::class, 'update'])->name('pages.sections.update');
            Route::delete('pages/{page:id}/sections/{section:id}', [PageSectionController::class, 'destroy'])->name('pages.sections.destroy');

            /*
             * The campaign register. Its own section rather than under
             * content: a campaign is marketing configuration that nothing
             * on the public site renders.
             *
             * Route::resource — Campaign has no slug, so the default
             * binding is already the id.
             */
            Route::resource('campaigns', CampaignController::class)->except('show');

            // --- enquiries ------------------------------------------------
            Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
            Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
            Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');

            /*
             * The inbox is a VIEW of leads where type = 'contact', not a
             * second table — see ContactMessageService. Declared before
             * the {lead} routes above would be ambiguous, so it gets its
             * own prefix rather than sharing one.
             */
            Route::get('contact-messages', [ContactMessageController::class, 'index'])->name('contact-messages.index');
            Route::patch('contact-messages/{lead}/handled', [ContactMessageController::class, 'toggleHandled'])->name('contact-messages.handled');

            /*
             * Visitor journeys.
             *
             * The two export routes are declared BEFORE the {visitorSession}
             * one, or "visitor-sessions/export" would be swallowed by
             * "visitor-sessions/{visitorSession}" and Laravel would try to
             * bind a model with the id "export".
             */
            Route::get('visitor-sessions/export', [VisitorSessionController::class, 'export'])->name('visitor-sessions.export');
            Route::get('visitor-sessions', [VisitorSessionController::class, 'index'])->name('visitor-sessions.index');
            Route::get('visitor-sessions/{visitorSession}/export', [VisitorSessionController::class, 'exportOne'])->name('visitor-sessions.export-one');
            Route::get('visitor-sessions/{visitorSession}', [VisitorSessionController::class, 'show'])->name('visitor-sessions.show');

            /*
             * Appointments.
             *
             * "bookings/agenda" is declared BEFORE "bookings/{booking}",
             * or the day view would be swallowed by the detail route and
             * Laravel would try to bind a booking with the id "agenda".
             */
            Route::get('bookings/agenda', [BookingController::class, 'agenda'])->name('bookings.agenda');
            Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
            Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
            Route::patch('bookings/{booking}/status', [BookingController::class, 'transition'])->name('bookings.status');

            // When the studio is open, and the one-off closures.
            Route::get('booking-availability', [BookingAvailabilityController::class, 'edit'])->name('booking-availability.edit');
            Route::put('booking-availability', [BookingAvailabilityController::class, 'update'])->name('booking-availability.update');
            Route::post('booking-availability/blackouts', [BookingAvailabilityController::class, 'storeBlackout'])->name('booking-availability.blackouts.store');
            Route::delete('booking-availability/blackouts/{blackoutDate}', [BookingAvailabilityController::class, 'destroyBlackout'])->name('booking-availability.blackouts.destroy');

            /*
             * Client-portal orders.
             *
             * Bound by ID here, not by reference. Order::getRouteKeyName()
             * returns 'reference' for the PUBLIC side; the dashboard binds
             * on the primary key so an admin correcting a reference cannot
             * 404 their own bookmarked URL — the same rule the slugged
             * resources follow.
             */
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
            Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
            Route::get('orders/{order:id}', [OrderController::class, 'show'])->name('orders.show');
            Route::get('orders/{order:id}/edit', [OrderController::class, 'edit'])->name('orders.edit');
            Route::match(['put', 'patch'], 'orders/{order:id}', [OrderController::class, 'update'])->name('orders.update');
            Route::patch('orders/{order:id}/status', [OrderController::class, 'transition'])->name('orders.status');
            Route::patch('orders/{order:id}/progress', [OrderController::class, 'updateProgress'])->name('orders.progress');
            Route::post('orders/{order:id}/code', [OrderController::class, 'reissueCode'])->name('orders.code');

            // The timeline. Nested, so both ids arrive separately — the
            // controller checks the event belongs to the order.
            Route::post('orders/{order:id}/events', [OrderController::class, 'storeEvent'])->name('orders.events.store');
            Route::match(['put', 'patch'], 'orders/{order:id}/events/{event:id}', [OrderController::class, 'updateEvent'])->name('orders.events.update');
            Route::delete('orders/{order:id}/events/{event:id}', [OrderController::class, 'destroyEvent'])->name('orders.events.destroy');

            // --- moderation ---------------------------------------------
            Route::get('comments', [CommentController::class, 'index'])->name('comments.index');
            Route::patch('comments/{comment}/approve', [CommentController::class, 'approve'])->name('comments.approve');
            Route::patch('comments/{comment}/reject', [CommentController::class, 'reject'])->name('comments.reject');
            Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

            // --- media ---------------------------------------------------
            // `medium` as the parameter name: Laravel singularises "media"
            // to "medium", and letting the two disagree is how a route
            // model binding silently stops resolving.
            Route::resource('media', MediaController::class)
                ->only(['index', 'store', 'destroy'])
                ->parameters(['media' => 'medium']);

            /*
             * Navigation.
             *
             * reorder and visibility are declared BEFORE the resource, so
             * "menu/reorder" is matched by its own route rather than being
             * swallowed by "menu/{menu}" and then failing to bind.
             */
            Route::post('menu/reorder', [MenuItemController::class, 'reorder'])->name('menu.reorder');
            Route::patch('menu/{menu}/visibility', [MenuItemController::class, 'toggleVisibility'])->name('menu.visibility');
            Route::resource('menu', MenuItemController::class)->except('show')->parameters(['menu' => 'menu']);

            // --- configuration -------------------------------------------
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

            Route::get('tracking', [TrackingSettingController::class, 'edit'])->name('tracking.edit');
            Route::put('tracking', [TrackingSettingController::class, 'update'])->name('tracking.update');

            Route::patch('locale', [LocaleController::class, 'update'])->name('locale.update');
        });
    });
