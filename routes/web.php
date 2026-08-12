<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SitemapController;
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

    Route::get('/about', [PageController::class, 'about'])->name("pages.about{$nameSuffix}");
    Route::get('/our-process', [PageController::class, 'ourProcess'])->name("pages.our-process{$nameSuffix}");
    Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name("pages.privacy-policy{$nameSuffix}");
    Route::get('/terms-and-conditions', [PageController::class, 'termsAndConditions'])->name("pages.terms-and-conditions{$nameSuffix}");
};

// Arabic — canonical names, no prefix.
Route::middleware('setlocale')->group(fn () => $registerPublicRoutes());

// English — "{name}.en" names, "en" prefix.
Route::prefix('en')->middleware('setlocale')->group(fn () => $registerPublicRoutes('.en'));

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
