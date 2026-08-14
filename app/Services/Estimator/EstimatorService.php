<?php

namespace App\Services\Estimator;

use App\Models\EstimatorItem;
use App\Models\Package;
use Illuminate\Support\Collection;

/**
 * Turns a selection into a quote.
 *
 * THE PRICES ARE ALWAYS RE-READ FROM THE DATABASE. Nothing in a submitted
 * payload contributes a number — the client sends keys, and this class
 * looks up what those keys cost. A total computed from a submitted price
 * is a total the visitor chose, and it would be the figure the business
 * then saw on its own leads screen.
 *
 * WHAT AN UNPRICED LINE MEANS, and why there are two different kinds:
 *
 *   estimator_items.price is NOT NULL, so an item with no price yet is
 *   expressed as is_active = false and never reaches the form at all (see
 *   EstimatorItemSeeder). There is no such thing as a selectable estimator
 *   line without a price.
 *
 *   packages.price IS nullable, and null means "on request" — a real and
 *   different fact from 0.00. A package is still shown while unpriced,
 *   because the visitor may well want to start from it, so a selection CAN
 *   legitimately contain something with no number attached.
 *
 * That asymmetry is the reason a quote is not just a sum. When part of the
 * selection is priced-on-request the total is a FLOOR, not a total, and
 * this class says so rather than quietly summing what it happens to know —
 * which would show a visitor a smaller number than they will be charged.
 */
class EstimatorService
{
    /**
     * The fallback when nothing priced has been selected yet. Every
     * seeded row carries SAR; this exists so the view has something to
     * print before any selection is made.
     */
    public const DEFAULT_CURRENCY = 'SAR';

    /**
     * A hard ceiling on how many item keys a submission may carry.
     *
     * The catalogue is a couple of dozen rows, so this is far above any
     * real selection. It exists because the keys arrive as an array from
     * the client and reach a whereIn.
     */
    public const MAX_SELECTED_ITEMS = 100;

    /**
     * The catalogue the public form renders: active items only, grouped
     * and ordered, plus the active packages a visitor can start from.
     *
     * One indexed read per table — the estimator page renders every row it
     * fetches, so there is nothing to defer.
     *
     * `has_pricing` is what decides whether the estimator opens at all,
     * and it is NOT simply "are there rows".
     *
     * A fresh install seeds every estimator item at 0.00 and inactive, and
     * every package with price = null — so there ARE rows, and rendering a
     * calculator from them would give a visitor a picker where nothing has
     * a price and any selection totals zero. That is a worse lie than
     * having no estimator, so the page needs at least one line carrying a
     * real figure before it shows a calculator at all.
     *
     * @return array{
     *     groups: Collection<string, Collection<int, EstimatorItem>>,
     *     packages: Collection<int, Package>,
     *     has_pricing: bool
     * }
     */
    public function catalogue(): array
    {
        $groups = EstimatorItem::grouped();
        $packages = Package::query()->active()->ordered()->get();

        return [
            'groups' => $groups,
            'packages' => $packages,
            // An active estimator item always carries a price above zero
            // (the dashboard refuses to activate one that does not), so
            // its mere presence is enough. A package has to be checked,
            // because an active one can still be priced-on-request.
            'has_pricing' => $groups->isNotEmpty()
                || $packages->contains(fn (Package $package) => ! $package->isPriceOnRequest()),
        ];
    }

    /**
     * The quote for a selection.
     *
     * @param  array<int, mixed>  $itemKeys
     * @return array{
     *     package: ?Package,
     *     items: Collection<int, EstimatorItem>,
     *     total: ?string,
     *     currency: string,
     *     on_request: list<string>,
     *     is_partial: bool,
     *     has_selection: bool
     * }
     */
    public function quote(?string $packageSlug, array $itemKeys): array
    {
        $package = $this->resolvePackage($packageSlug);
        $items = $this->resolveItems($itemKeys);

        // Priced-on-request lines, by label, so the view and the stored
        // snapshot can both name exactly what is missing from the figure.
        // Rendered strings, deliberately, unlike the snapshot's label maps
        // below: this list is a human-readable note ("X is priced on
        // request") shown next to the figure, not data anything reads back.
        $onRequest = $package !== null && $package->isPriceOnRequest()
            ? [$package->name]
            : [];

        $currencies = $items->pluck('currency')
            ->when($package?->price !== null, fn (Collection $c) => $c->push($package->currency))
            ->filter()
            ->unique();

        /*
         * More than one currency in a single selection cannot be summed,
         * and converting would mean inventing a rate. The quote degrades
         * to "no total" rather than producing a number that is wrong in
         * every currency it might be read as.
         */
        $summable = $currencies->count() <= 1;

        $total = $items->sum(fn (EstimatorItem $item) => (float) $item->price)
            + ($package?->price !== null ? (float) $package->price : 0.0);

        $hasPricedLine = $items->isNotEmpty() || $package?->price !== null;

        return [
            'package' => $package,
            'items' => $items,
            'total' => $hasPricedLine && $summable ? number_format($total, 2, '.', '') : null,
            'currency' => $currencies->first() ?? self::DEFAULT_CURRENCY,
            'on_request' => $onRequest,
            // A floor rather than a total: something in the selection has
            // no price, so the real figure can only be higher.
            'is_partial' => $onRequest !== [] || ! $summable,
            'has_selection' => $package !== null || $items->isNotEmpty(),
        ];
    }

    /**
     * The immutable record of what this visitor was quoted.
     *
     * Labels and prices are COPIED rather than referenced. Catalogue
     * prices change and labels get rewritten; a lead has to keep showing
     * what was on the screen at the time, not what the price list says
     * today. `key` is kept alongside so a line can still be traced back to
     * its catalogue row when it still exists.
     *
     * @param  array<string, mixed>  $quote  as returned by quote()
     * @return array<string, mixed>
     */
    public function snapshot(array $quote): array
    {
        /** @var Collection<int, EstimatorItem> $items */
        $items = $quote['items'];
        /** @var ?Package $package */
        $package = $quote['package'];

        return [
            'quoted_at' => now()->toIso8601String(),
            'currency' => $quote['currency'],
            'total' => $quote['total'],
            // Stated in the file itself, so a reader of an old lead knows
            // the number is a floor without having to infer it.
            'is_partial' => $quote['is_partial'],
            'on_request' => $quote['on_request'],
            /*
             * getTranslations(), not the rendered accessor.
             *
             * `$item->label` gives the string for whatever locale the
             * VISITOR was browsing in, and storing that would leave an
             * English-reading admin looking at an Arabic breakdown — or
             * vice versa. The full per-locale map is just as immutable and
             * reads correctly for both.
             */
            'package' => $package === null ? null : [
                'slug' => $package->slug,
                'name' => $package->getTranslations('name'),
                'price' => $package->isPriceOnRequest() ? null : number_format((float) $package->price, 2, '.', ''),
            ],
            'items' => $items->map(fn (EstimatorItem $item) => [
                'key' => $item->key,
                'label' => $item->getTranslations('label'),
                'group' => $item->group,
                'price' => number_format((float) $item->price, 2, '.', ''),
            ])->values()->all(),
        ];
    }

    /**
     * Active items for the submitted keys, in catalogue order.
     *
     * Unknown, inactive and duplicate keys are dropped rather than
     * rejected: a stale bookmarked selection, or an item withdrawn between
     * the page loading and the form being submitted, should still produce
     * a sensible quote rather than a validation error the visitor cannot
     * act on.
     *
     * @param  array<int, mixed>  $keys
     * @return Collection<int, EstimatorItem>
     */
    private function resolveItems(array $keys): Collection
    {
        $keys = collect($keys)
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->unique()
            ->take(self::MAX_SELECTED_ITEMS)
            ->values();

        if ($keys->isEmpty()) {
            return collect();
        }

        return EstimatorItem::query()
            ->active()
            ->whereIn('key', $keys->all())
            ->ordered()
            ->get();
    }

    private function resolvePackage(?string $slug): ?Package
    {
        if ($slug === null || trim($slug) === '') {
            return null;
        }

        // active(): a package withdrawn from sale must not be quotable,
        // and a bookmarked slug pointing at one degrades to "no base
        // package" rather than erroring.
        return Package::query()->active()->where('slug', $slug)->first();
    }
}
