<?php

use App\Http\Controllers\Dashboard\DashboardHomeController;
use App\Http\Controllers\Dashboard\LocaleController;
use App\Http\Controllers\Dashboard\SettingsController;
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
| unsafe methods — the limiter returns Limit::none() for GET/HEAD (see
| AppServiceProvider::configureRateLimiting), so browsing is unrationed
| while every write is capped per admin. Without it a stolen session had
| no ceiling at all.
*/
Route::prefix('dashboard')
    ->middleware(['auth', 'admin', 'dashboardlocale', 'throttle:dashboard-writes'])
    ->group(function () {
        Route::get('/', [DashboardHomeController::class, 'index'])->name('dashboard');

        Route::name('dashboard.')->group(function () {
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

            Route::patch('locale', [LocaleController::class, 'update'])->name('locale.update');
        });
    });
