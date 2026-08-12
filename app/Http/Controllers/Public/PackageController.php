<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function index(): View
    {
        $packages = Package::active()->ordered()->get();

        return view('public.packages.index', [
            'packages' => $packages,
            'comparisonRows' => $this->comparisonRows($packages),
        ]);
    }

    /**
     * The rows for the comparison matrix — or an EMPTY collection when the
     * data is not actually comparable.
     *
     * WHY THE GUARD. A matrix only says something useful when packages
     * SHARE features, so the ticks form a meaningful pattern. The seeded
     * lists are incremental instead ("Everything in Starter", then only
     * what that tier adds), which is a perfectly reasonable way to write
     * them — but rendered as a matrix it produces one tick per row and
     * reads as "Enterprise does not include a logo", which is the
     * opposite of true.
     *
     * So the table renders only when at least one feature appears in more
     * than one package. With incremental lists nothing repeats, the table
     * is skipped, and the cards carry the comparison on their own. Write
     * the lists cumulatively and the matrix appears and is correct — no
     * setting, no flag, just the data deciding.
     *
     * @param  Collection<int, Package>  $packages
     * @return Collection<int, string>
     */
    private function comparisonRows(Collection $packages): Collection
    {
        if ($packages->count() < 2) {
            return collect();
        }

        $counts = $packages
            ->flatMap(fn (Package $package) => array_unique($package->featureList()))
            ->countBy();

        if ($counts->filter(fn (int $count) => $count > 1)->isEmpty()) {
            return collect();
        }

        // First-seen order, so the rows follow the packages' own ordering
        // rather than an arbitrary sort.
        return $packages
            ->flatMap(fn (Package $package) => $package->featureList())
            ->unique()
            ->values();
    }
}
