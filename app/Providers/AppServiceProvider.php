<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Country;
use App\Models\Package;
use App\Models\Page;
use App\Models\Service;
use App\Services\Analytics\IpAnonymizer;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
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
