<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\EstimatorItem;
use App\Models\Faq;
use App\Models\Package;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Service;
use App\Models\Testimonial;
use App\Services\Cms\SettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * The homepage.
 *
 * Every section reads from the database rather than from hardcoded
 * markup, so the page an admin sees after editing content is the page
 * visitors get. Each query is bounded and rides an existing composite
 * index (see the create_* migrations), so the whole page is a fixed
 * handful of indexed reads no matter how much content accumulates.
 *
 * Sections whose data is empty are not rendered at all — a fresh install
 * with no testimonials shows no testimonials section rather than an empty
 * heading, which is the normal state here since testimonials are demo-only
 * data.
 */
class HomeController extends Controller
{
    /** Enough to fill the grid without turning the homepage into an index. */
    private const SERVICE_LIMIT = 8;

    private const ARTICLE_LIMIT = 3;

    private const FAQ_LIMIT = 5;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(): View
    {
        return view('public.home', [
            /*
             * The homepage is the one page with no backing record to carry
             * a SeoMeta row, so its title and description come from the
             * dedicated home_seo settings instead. Without this it would
             * fall through to the site-wide default — which is exactly how
             * the most-shared page on a site ends up with a generic title.
             */
            'seoTitle' => $this->settings->translated('home_meta_title'),
            'seoDescription' => $this->settings->translated('home_meta_description'),

            /*
             * The four economic sectors, read from the Activities page's
             * own sections rather than duplicated into this file.
             *
             * One source of truth: the homepage grid and /activities
             * render the same rows, so an admin who reworded a sector in
             * the dashboard changes both. An empty collection — page
             * unpublished, or sections deactivated — renders no section
             * at all rather than an empty heading.
             */
            'activities' => $this->sectionsOf('activities'),
            'whySaudi' => $this->sectionsOf('why-invest-saudi-arabia'),

            'services' => Service::query()
                ->active()
                ->ordered()
                ->limit(self::SERVICE_LIMIT)
                ->get(),

            'packages' => Package::query()
                ->active()
                ->ordered()
                ->get(),

            'articles' => Article::query()
                ->live()
                ->latest('published_at')
                ->limit(self::ARTICLE_LIMIT)
                ->get(),

            'testimonials' => Testimonial::query()
                ->active()
                ->ordered()
                ->get(),

            'faqs' => Faq::query()
                ->active()
                ->ordered()
                ->limit(self::FAQ_LIMIT)
                ->get(),

            // The estimator teaser shows a real, cheapest-to-most-expensive
            // range built from priced items — never an invented figure. On
            // a fresh install nothing is priced (EstimatorItemSeeder ships
            // its catalogue inactive on purpose), so this is null and the
            // teaser renders its "pricing in preparation" state instead.
            'estimatorRange' => $this->estimatorRange(),
        ]);
    }

    /**
     * A published page's active sections, or an empty collection.
     *
     * Deliberately tolerant: a missing or unpublished page is a section
     * the homepage simply does not render, not an error. That is the same
     * rule the testimonials block already follows, and it is what lets
     * this page survive an admin unpublishing something.
     *
     * @return Collection<int, PageSection>
     */
    private function sectionsOf(string $slug): Collection
    {
        $page = Page::query()
            ->published()
            ->where('slug', $slug)
            ->with(['sections' => fn ($query) => $query->active()->ordered()])
            ->first();

        return $page?->sections ?? collect();
    }

    /**
     * The span between the cheapest and the most expensive active
     * estimator item, or null when nothing has been priced yet.
     *
     * One aggregate query, not a fetch-and-sum in PHP.
     *
     * @return array{min: float, max: float, count: int}|null
     */
    private function estimatorRange(): ?array
    {
        $stats = EstimatorItem::query()
            ->active()
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price, COUNT(*) as item_count')
            ->first();

        if (! $stats || (int) $stats->item_count === 0 || $stats->max_price === null) {
            return null;
        }

        return [
            'min' => (float) $stats->min_price,
            'max' => (float) $stats->max_price,
            'count' => (int) $stats->item_count,
        ];
    }
}
