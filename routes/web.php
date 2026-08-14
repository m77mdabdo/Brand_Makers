<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\CommentController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\CountryController;
use App\Http\Controllers\Public\EstimatorController;
use App\Http\Controllers\Public\FaqController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PackageController;
use App\Http\Controllers\Public\PackageEnquiryController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\ServiceController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\VisitorBeaconController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes — bilingual
|--------------------------------------------------------------------------
| Arabic is the default and carries NO url prefix (/, /about, ...);
| English lives under /en/... (/en, /en/about, ...).
|
| A plain optional {locale?} prefix does not work for this: Laravel's route
| compilation does not support an optional parameter followed by required
| literal segments, so `{locale?}/about` never matches `/about` once the
| locale is omitted — it only ever matches `/en/about`.
|
| So every public route is registered TWICE from the same closure: once
| with canonical names and no prefix (Arabic), once with an ".en" name
| suffix under an "en" prefix (English). Views call lroute() instead of
| route() (see app/helpers.php); it resolves to the ".en" variant when the
| locale is English and falls through untouched otherwise, so it is always
| safe to use — including on single-locale routes that have no variant.
|
| The route NAME is also what SetLocale reads to decide the locale, so the
| router's decision and the rendered language can never disagree.
*/
$registerPublicRoutes = function (string $nameSuffix = ''): void {
    Route::get('/', [HomeController::class, 'index'])->name("home{$nameSuffix}");

    /*
     * The {model:slug} binding is EXPLICIT rather than left to
     * getRouteKeyName(), so the public URL contract is visible in this
     * file. The dashboard binds the same models by id (see
     * routes/dashboard.php) — stating both at the point of use is what
     * stops one of them silently changing when a model is edited.
     */
    Route::get('/services', [ServiceController::class, 'index'])->name("services.index{$nameSuffix}");
    Route::get('/services/{service:slug}', [ServiceController::class, 'show'])->name("services.show{$nameSuffix}");

    Route::get('/packages', [PackageController::class, 'index'])->name("packages.index{$nameSuffix}");
    /*
     * throttle:5,1 on every public WRITE.
     *
     * Five per minute per IP is far above anyone filling in a form by
     * hand and far below what makes automated submission worthwhile. It
     * is applied per route rather than to the whole public group because
     * the group is mostly GETs, and rationing those would throttle
     * ordinary browsing.
     */
    Route::post('/packages/enquire', [PackageEnquiryController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name("packages.enquire{$nameSuffix}");

    /*
     * The cost estimator.
     *
     * URL "/cost-estimator", route NAME "estimator". The name is what the
     * navigation whitelist and the seeded menu item already reference (see
     * MenuItem::ROUTES), so registering it here is what lights up the
     * dormant "حاسبة التكلفة / Cost Estimator" entry in the navbar.
     */
    Route::get('/cost-estimator', [EstimatorController::class, 'show'])->name("estimator{$nameSuffix}");
    Route::post('/cost-estimator', [EstimatorController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name("estimator.store{$nameSuffix}");

    /*
     * Appointment booking.
     *
     * URL "/book", route NAME "booking" — the name MenuItem::ROUTES
     * whitelists and the seeded menu item already points at, so
     * registering it is what lights up the dormant "احجز استشارة / Book a
     * Consultation" entry in the navbar.
     */
    Route::get('/book', [BookingController::class, 'show'])->name("booking{$nameSuffix}");
    Route::post('/book', [BookingController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name("booking.store{$nameSuffix}");

    Route::get('/countries', [CountryController::class, 'index'])->name("countries.index{$nameSuffix}");

    Route::get('/faqs', [FaqController::class, 'index'])->name("faqs.index{$nameSuffix}");

    Route::get('/articles', [ArticleController::class, 'index'])->name("articles.index{$nameSuffix}");
    Route::get('/articles/{article:slug}', [ArticleController::class, 'show'])->name("articles.show{$nameSuffix}");
    Route::post('/articles/{article:slug}/comments', [CommentController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name("articles.comments.store{$nameSuffix}");

    Route::get('/contact', [ContactController::class, 'show'])->name("contact{$nameSuffix}");
    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name("contact.store{$nameSuffix}");

    Route::get('/about', [PageController::class, 'about'])->name("pages.about{$nameSuffix}");
    Route::get('/our-process', [PageController::class, 'ourProcess'])->name("pages.our-process{$nameSuffix}");
    Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name("pages.privacy-policy{$nameSuffix}");
    Route::get('/terms-and-conditions', [PageController::class, 'termsAndConditions'])->name("pages.terms-and-conditions{$nameSuffix}");
};

// Arabic — canonical names, no prefix.
//
// 'trackvisitor' records the visitor journey. It is on the PUBLIC groups
// only: an admin browsing the site from the dashboard is not a visitor,
// and the middleware itself writes nothing until the gate agrees —
// consent, DNT/GPC, the bot filter and the admin switch. See
// VisitorTrackingGate.
Route::middleware(['setlocale', 'trackvisitor'])->group(fn () => $registerPublicRoutes());

// English — "{name}.en" names, "en" prefix.
Route::prefix('en')->middleware(['setlocale', 'trackvisitor'])->group(fn () => $registerPublicRoutes('.en'));

/*
 * The time-on-page beacon.
 *
 * Registered ONCE, outside the dual-locale closure: it is an endpoint,
 * not a page, so it has no Arabic and English variants to keep in step.
 *
 * Deliberately NOT in the 'trackvisitor' group. It is a POST, so the
 * middleware would skip it anyway — keeping it out makes that independent
 * of the middleware's internals rather than a consequence of them.
 *
 * Throttled hard. A beacon fires a handful of times per page at most, so
 * 60/minute is far above real use and well below what would let anyone
 * inflate a session's duration by hammering it.
 *
 * It keeps CSRF: sendBeacon cannot set headers, so the client sends the
 * token as a `_token` form field, which is exactly what Laravel's CSRF
 * middleware already reads.
 */
Route::post('_visitor/beacon', VisitorBeaconController::class)
    ->middleware('throttle:60,1')
    ->name('visitor.beacon');

// Single document listing BOTH locales, so it is registered once rather
// than through the dual-locale closure above.
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

/*
|--------------------------------------------------------------------------
| Authenticated staff
|--------------------------------------------------------------------------
*/

// Same write throttle as the dashboard group — profile update and account
// deletion are authenticated writes with no other ceiling on them.
Route::middleware(['auth', 'dashboardlocale', 'throttle:dashboard-writes'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/dashboard.php';
