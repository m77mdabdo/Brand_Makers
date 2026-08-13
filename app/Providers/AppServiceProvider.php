<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Country;
use App\Models\MenuItem;
use App\Models\Package;
use App\Models\Page;
use App\Models\Service;
use App\Services\Analytics\IpAnonymizer;
use App\Services\Cms\SettingsRepository;
use App\Services\Crm\ContactMessageService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Translatable\Facades\Translatable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The ONLY models that may be attached to a SeoMeta record.
     *
     * Two things happen here. The stored seo_metable_type becomes a short
     * alias instead of a fully-qualified class name, so renaming or moving
     * a class never orphans the rows pointing at it. And because the map
     * is ENFORCED (below), attaching SEO metadata to a model that is not
     * listed throws immediately instead of silently writing a row that
     * nothing will ever resolve.
     *
     * @var array<string, class-string>
     */
    public const MORPH_MAP = [
        'page' => Page::class,
        'article' => Article::class,
        'service' => Service::class,
        'country' => Country::class,
        'package' => Package::class,
    ];

    public function register(): void
    {
        // Stateless, and reached from model mutators on a hot path — one
        // instance for the whole request.
        $this->app->singleton(IpAnonymizer::class);

        // Singleton so the settings table is read at most once per
        // request, however many callers ask for a value.
        $this->app->singleton(SettingsRepository::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(self::MORPH_MAP);

        $this->configureTrustedProxies();
        $this->forceHttpsInProduction();
        $this->configureRateLimiting();

        // spatie/laravel-translatable ships no publishable config file in
        // this version, so this is the actual equivalent of setting the
        // package's fallback_locale. Without it a record with no English
        // translation yet renders blank on /en instead of showing its
        // Arabic value.
        Translatable::fallback(fallbackLocale: config('app.fallback_locale'));

        $this->composePublicLayout();
        $this->composeDashboardSidebar();
    }

    /**
     * Rate limits.
     *
     * The dashboard had no ceiling at all, so a stolen session could
     * drive unlimited writes. Safe methods return Limit::none() so the
     * limiter can be applied to the WHOLE dashboard group without
     * rationing ordinary browsing — only writes are counted.
     *
     * Keyed on the user id (falling back to IP for the theoretically
     * unauthenticated case), so two admins never share a bucket.
     * 60/minute is far above human editing speed: this is a
     * runaway/abuse ceiling, not a usability constraint.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('dashboard-writes', function (Request $request) {
            if ($request->isMethodSafe()) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });
    }

    /**
     * Data every public page's chrome needs.
     *
     * A view composer rather than a parameter on every controller: the
     * navbar, the footer and the floating buttons render on every public
     * page, and none of the controllers otherwise care about settings or
     * menu items. Two queries total — the settings singleton, plus the
     * menu tree with its visible children eager-loaded.
     */
    private function composePublicLayout(): void
    {
        View::composer('components.layouts.public', function ($view) {
            $settings = app(SettingsRepository::class);

            $view->with([
                'siteName' => $settings->translated('site_name') ?? __('site.brand.name'),
                'siteLogo' => $settings->string('site_logo'),
                'contactWhatsapp' => $settings->string('contact_whatsapp'),
                'contactEmail' => $settings->string('contact_email'),
                'contactPhone' => $settings->string('contact_phone'),
                'contactAddress' => $settings->translated('contact_address'),
                'socialLinks' => $settings->socialLinks(),
                'menu' => $this->publicMenu(),
            ]);
        });
    }

    /**
     * The unread badge on the Contact Messages sidebar item.
     *
     * Composed onto the sidebar rather than passed from each controller,
     * because every dashboard section renders it and none of them
     * otherwise care about leads. One indexed COUNT per dashboard page
     * render, covered by leads_type_is_handled_index.
     */
    private function composeDashboardSidebar(): void
    {
        View::composer('components.dashboard.sidebar', function ($view) {
            $view->with('unhandledContactMessages', app(ContactMessageService::class)->unhandledCount());
        });
    }

    /**
     * The visible, ordered navbar tree.
     *
     * Two queries (parents plus their children), not one per item.
     * Children are eager-loaded ALREADY filtered to visible ones, so a
     * hidden child can never leak into a rendered dropdown.
     *
     * @return Collection<int, MenuItem>
     */
    private function publicMenu(): Collection
    {
        return MenuItem::query()
            ->topLevel()
            ->visible()
            ->ordered()
            ->with('visibleChildren')
            ->get();
        // Two queries total (parents, then their visible children) — not
        // one per item. isActive() only consults children that are
        // already loaded, so this eager load is what keeps the highlight
        // free rather than N+1.
    }

    /**
     * Trust the load balancer in front of us.
     *
     * Without this, a TLS-terminating proxy makes every request look like
     * plain http to the app: $request->secure() is false, so a `secure`
     * session cookie is never sent and the session breaks; generated URLs
     * come out as http:// on an https:// site; and $request->ip() is the
     * balancer's address, so rate limiting keys every visitor to one
     * bucket.
     *
     * Configured HERE rather than in bootstrap/app.php because config()
     * is not bound while the middleware stack is being built — see the
     * note there.
     */
    private function configureTrustedProxies(): void
    {
        TrustProxies::at(config('trustedproxy.proxies'));

        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB
        );
    }

    /**
     * Generate https:// links in production.
     *
     * Gated on APP_URL actually being https, so a deployment that
     * genuinely runs over http is not broken by this, and never applied
     * locally where forcing https would break the dev server outright.
     */
    private function forceHttpsInProduction(): void
    {
        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
