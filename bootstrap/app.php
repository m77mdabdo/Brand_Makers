<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetDashboardLocale;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackVisitorSession;
use App\Services\Analytics\VisitorTrackingGate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global: every web response gets the security headers.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        /*
         * Trusted proxies are configured in AppServiceProvider::boot(),
         * NOT here.
         *
         * This closure runs while the kernel is being resolved, before the
         * config repository is bound — calling config() here throws
         * "Target class [config] does not exist". Reading env() instead
         * would appear to work and then silently return null in
         * production, because a cached config means .env is never loaded.
         *
         * TrustProxies is already in Laravel's default global stack, and
         * its static at()/withHeaders() configurators are read when the
         * middleware runs, which is long after providers boot.
         */

        /*
         * The two attribution cookies are read and written by JavaScript
         * as well as by PHP, so they cannot be encrypted — an encrypted
         * cookie is opaque to the client that has to read it.
         *
         * Safe to except: both carry only UTM parameters and click IDs
         * that were already visible in the URL the visitor arrived on.
         * Neither is an identifier, and neither is trusted — the server
         * treats their contents as untrusted input and validates before
         * storing (see AttributionService).
         */
        /*
         * The consent cookie joins them for the same reason: the banner
         * is an Alpine component, so JavaScript is what writes it, and
         * the server has to read exactly what JavaScript wrote. It holds
         * one of two literal words and no identifier.
         *
         * VisitorSessionService::COOKIE is deliberately NOT excepted. It
         * is written and read only by PHP, it is httpOnly, and it is the
         * key to a journey row — so it gets the encryption the other
         * three cannot have.
         */
        $middleware->encryptCookies(except: [
            'cm_first_touch',
            'cm_latest_touch',
            VisitorTrackingGate::CONSENT_COOKIE,
        ]);

        $middleware->alias([
            'setlocale' => SetLocale::class,
            'dashboardlocale' => SetDashboardLocale::class,
            'admin' => EnsureUserIsAdmin::class,
            'trackvisitor' => TrackVisitorSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Exception responses (a 404, an auth redirect) are rendered by the
        // handler and never bubble back out through the middleware stack,
        // so without this they would ship with no security headers at all.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            return SecurityHeaders::apply($response, $request);
        });
    })->create();
