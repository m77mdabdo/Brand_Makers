<?php

use App\Http\Controllers\Dashboard\ArticleController;
use App\Http\Controllers\Dashboard\CommentController;
use App\Http\Controllers\Dashboard\ContactMessageController;
use App\Http\Controllers\Dashboard\CountryController;
use App\Http\Controllers\Dashboard\DashboardHomeController;
use App\Http\Controllers\Dashboard\FaqController;
use App\Http\Controllers\Dashboard\LeadController;
use App\Http\Controllers\Dashboard\LocaleController;
use App\Http\Controllers\Dashboard\MediaController;
use App\Http\Controllers\Dashboard\MenuItemController;
use App\Http\Controllers\Dashboard\PageController;
use App\Http\Controllers\Dashboard\PageSectionController;
use App\Http\Controllers\Dashboard\ServiceController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\TestimonialController;
use App\Http\Controllers\Dashboard\TrackingSettingController;
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
            $idBound('countries', CountryController::class, 'country');
            $idBound('articles', ArticleController::class, 'article');

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
