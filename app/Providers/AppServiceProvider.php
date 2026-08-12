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
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
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

        $this->forceHttpsInProduction();

        // spatie/laravel-translatable ships no publishable config file in
        // this version, so this is the actual equivalent of setting the
        // package's fallback_locale. Without it a record with no English
        // translation yet renders blank on /en instead of showing its
        // Arabic value.
        Translatable::fallback(fallbackLocale: config('app.fallback_locale'));

        $this->composePublicLayout();
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
