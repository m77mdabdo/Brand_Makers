<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| REQUIRES A CRON ENTRY ON THE SERVER, or none of this runs:
|
|     * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
|
| Worth stating loudly, because the retention prune below is a privacy
| commitment rather than a cleanup nicety — the privacy policy tells
| visitors their session data is deleted after the configured window, and
| without that cron entry the sentence is untrue.
|
*/

// 03:10 rather than 03:00: every scheduler in the world fires on the hour,
// and this shares a host with whatever else is on it.
Schedule::command('visitor-sessions:prune')
    ->dailyAt('03:10')
    ->onOneServer()
    // The job is idempotent — it deletes whatever is already past the
    // window — so a run overlapping a slow previous one would only
    // duplicate work.
    ->withoutOverlapping();
