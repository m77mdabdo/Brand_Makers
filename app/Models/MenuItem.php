<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;
use Spatie\Translatable\HasTranslations;

class MenuItem extends Model
{
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public const LINK_TYPES = ['route', 'url', 'none'];

    /** @var list<string> */
    public const TARGETS = ['_self', '_blank'];

    /**
     * The ONLY route names a menu item may point at.
     *
     * Every entry is a parameter-free public route. Routes that need a
     * bound model (services.show, articles.show) are deliberately absent:
     * a menu entry pointing at one could not be resolved without a
     * parameter and would 500 the navbar on every page of the site.
     *
     * Keys are route names; values are the translation key used to label
     * them in the dashboard's route picker.
     *
     * @var array<string, string>
     */
    public const ROUTE_WHITELIST = [
        'home' => 'dashboard.menu.routes.home',
        'services.index' => 'dashboard.menu.routes.services',
        'packages.index' => 'dashboard.menu.routes.packages',
        'estimator' => 'dashboard.menu.routes.estimator',
        'booking' => 'dashboard.menu.routes.booking',
        'orders.track' => 'dashboard.menu.routes.order_tracking',
        'countries.index' => 'dashboard.menu.routes.countries',
        'faqs.index' => 'dashboard.menu.routes.faqs',
        'articles.index' => 'dashboard.menu.routes.articles',
        'pages.about' => 'dashboard.menu.routes.about',
        'pages.our-process' => 'dashboard.menu.routes.our_process',
        'pages.privacy-policy' => 'dashboard.menu.routes.privacy_policy',
        'pages.terms-and-conditions' => 'dashboard.menu.routes.terms',
        'contact' => 'dashboard.menu.routes.contact',
    ];

    /**
     * `is_system` is guarded: it is what protects the core navigation from
     * deletion, so an admin-created item must never be able to claim it
     * through a form payload. Set it in code (see MenuItemSeeder).
     */
    protected $fillable = [
        'parent_id',
        'key',
        'label',
        'link_type',
        'route_name',
        'url',
        'target',
        'is_visible',
        'sort_order',
    ];

    /** @var list<string> */
    public $translatable = ['label'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_system' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->ordered();
    }

    /**
     * Children the public site should render — used by the navbar so a
     * hidden child never leaks into a dropdown.
     */
    public function visibleChildren(): HasMany
    {
        return $this->children()->visible();
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /**
     * Where this item points.
     *
     *   route → the named route, if it is whitelisted AND registered
     *   url   → used verbatim (it may be external, or a root-relative
     *           path the admin typed)
     *   none  → null; the caller renders a dropdown trigger, not a link
     *
     * Returns null rather than throwing on anything unresolvable — a bad
     * row must not take the navbar, and therefore every page, down with
     * it.
     */
    public function href(): ?string
    {
        return match ($this->link_type) {
            'url' => $this->resolveUrlHref(),
            'route' => $this->resolveRouteHref(),
            default => null,
        };
    }

    /**
     * A stored url is used verbatim, with ONE exception: a fragment-only
     * value ("#packages") means "that section of the homepage", and is
     * resolved against the CURRENT locale's homepage.
     *
     * Without this, a bare "#packages" in the navbar would scroll to a
     * section that only exists on the homepage — silently doing nothing on
     * every other page. Storing "/#packages" instead would be worse: it
     * hardcodes the Arabic homepage and would send an English visitor out
     * of /en on every click.
     */
    private function resolveUrlHref(): ?string
    {
        if (! $this->url) {
            return null;
        }

        if (str_starts_with($this->url, '#')) {
            return lroute('home').$this->url;
        }

        return $this->url;
    }

    private function resolveRouteHref(): ?string
    {
        if (! $this->route_name || ! array_key_exists($this->route_name, self::ROUTE_WHITELIST)) {
            return null;
        }

        // Route::has() as well as the whitelist: the whitelist says which
        // routes are ALLOWED, this says which are actually registered. A
        // whitelisted route that has not been built yet must render as a
        // dead entry, not a RouteNotFoundException on every page.
        //
        // lroute(), never route(): every public route is registered twice,
        // and a bare route() here would send an English visitor to the
        // Arabic URL from every navbar link on /en.
        return Route::has($this->route_name) ? lroute($this->route_name) : null;
    }

    /**
     * True when this item should render as a dropdown rather than a link.
     */
    public function hasVisibleChildren(): bool
    {
        return $this->relationLoaded('visibleChildren')
            ? $this->visibleChildren->isNotEmpty()
            : $this->visibleChildren()->exists();
    }

    /**
     * Whether this item is the page currently being viewed.
     *
     * A dropdown parent lights up when one of its children is active, so
     * the top-level entry stays highlighted while a visitor reads a page
     * nested beneath it.
     */
    public function isActive(): bool
    {
        if ($this->link_type === 'route' && $this->routeMatchesCurrent()) {
            return true;
        }

        // Only consults children that are already loaded. The navbar
        // eager-loads visibleChildren, so this costs nothing there; a
        // stray unloaded call must not turn one render into N queries.
        if ($this->relationLoaded('visibleChildren')) {
            return $this->visibleChildren->contains(fn (self $child) => $child->isActive());
        }

        return false;
    }

    /**
     * Exact match, plus the ONE generalisation worth making: an ".index"
     * item also owns its ".show" detail pages, so "Services" stays lit
     * while a visitor reads a single service.
     *
     * Deliberately NOT a loose prefix match on the first segment. That
     * would light up every pages.* item at once — About, Our Process,
     * Privacy and Terms all share the "pages" prefix — so a visitor
     * reading the privacy policy would see four highlighted nav entries.
     * It would also make "articles.index" match "articles.comments.store".
     *
     * Compares against current_route_base_name(), which strips the ".en"
     * suffix, so the highlight is identical in both locales.
     */
    private function routeMatchesCurrent(): bool
    {
        $current = current_route_base_name();

        if ($current === null || $this->route_name === null) {
            return false;
        }

        if ($this->route_name === $current) {
            return true;
        }

        return str_ends_with($this->route_name, '.index')
            && $current === substr($this->route_name, 0, -6).'.show';
    }

    /**
     * rel="noopener" is required on target="_blank" links — without it the
     * opened page can reach back through window.opener.
     */
    public function linkRel(): ?string
    {
        return $this->target === '_blank' ? 'noopener' : null;
    }
}
