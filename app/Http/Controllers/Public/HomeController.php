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
     * What the estimator can actually quote today: cheapest to dearest,
     * or null when nothing is priced.
     *
     * THIS USED TO READ ESTIMATOR ITEMS ONLY, and that was wrong in both
     * directions once the packages carried real figures.
     *
     * Too low: a fresh install has three packages at 13,000 / 22,000 /
     * 30,000 SAR and an add-on catalogue that ships unpriced, so the
     * teaser announced "الأسعار قيد الإعداد" on the same page whose
     * packages section was quoting 13,000 — the site contradicting
     * itself two screens apart.
     *
     * Too narrow: with a single add-on activated, MIN and MAX were the
     * same row and the teaser rendered "1 – 1", a range of one number.
     *
     * So the span covers BOTH sources, which is also what the estimator
     * itself sums — a visitor starts from a package and adds items, so a
     * range built from only half of that was never the real span.
     *
     * PRICES OF ZERO ARE EXCLUDED. EstimatorItemSeeder ships the
     * catalogue at 0.00 and inactive precisely because 0 is not a price,
     * and an admin who activates a row before pricing it would otherwise
     * drag the advertised minimum to zero — "from 0 SAR" on the homepage
     * being considerably worse than saying nothing yet.
     *
     * @return array{min: float, max: float, count: int}|null
     */
    private function estimatorRange(): ?array
    {
        $itemStats = EstimatorItem::query()
            ->active()
            ->where('price', '>', 0)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price, COUNT(*) as row_count')
            ->first();

        $packageStats = Package::query()
            ->active()
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price, COUNT(*) as row_count')
            ->first();

        $mins = array_filter([$itemStats?->min_price, $packageStats?->min_price], fn ($v) => $v !== null);
        $maxes = array_filter([$itemStats?->max_price, $packageStats?->max_price], fn ($v) => $v !== null);

        if ($mins === [] || $maxes === []) {
            return null;
        }

        return [
            'min' => (float) min($mins),
            'max' => (float) max($maxes),
            'count' => (int) ($itemStats?->row_count ?? 0) + (int) ($packageStats?->row_count ?? 0),
        ];
    }
}
