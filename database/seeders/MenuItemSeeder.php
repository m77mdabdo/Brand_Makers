<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Route;

/**
 * The core public navigation, as data.
 *
 * Every item is marked is_system: the admin may rename, hide or reorder
 * them freely, but deletion is guarded so the site cannot end up with no
 * navigation at all.
 *
 * Idempotent on `key`, which is exactly what that nullable-unique column
 * exists for. Matching on route_name instead would forbid an admin from
 * ever adding a second entry pointing at the same page — a legitimate
 * thing to want when one lives inside a dropdown.
 *
 * ROUTE OR ANCHOR. Each item names the route it will EVENTUALLY point at
 * plus the homepage section where that content lives TODAY. At seed time
 * the seeder picks whichever is real: a registered route becomes a route
 * link, and anything not built yet becomes a link to its homepage section,
 * which is where a visitor would actually find it right now.
 *
 * That keeps the navbar complete and free of dead entries at every stage
 * of the build, and re-seeding after a later step promotes those items to
 * real route links automatically — no migration, no manual edit.
 *
 * NOTE: labels are re-applied on every run. That is intentional for system
 * items (they should track the site's own wording), and it means an admin
 * who renames one will see it revert on a re-seed — re-seeding production
 * is not a routine operation. is_visible and sort_order are NOT re-applied:
 * hiding or reordering a core item is a legitimate admin choice.
 */
class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $sortOrder => $item) {
            $destination = $this->destinationFor($item);

            $existing = MenuItem::where('key', $item['key'])->first();

            if ($existing) {
                $existing->update(['label' => $item['label']] + $destination);

                $existing->is_system = true;
                $existing->save();

                continue;
            }

            $created = MenuItem::create([
                'key' => $item['key'],
                'label' => $item['label'],
                'target' => '_self',
                'parent_id' => null,
                'is_visible' => true,
                'sort_order' => $sortOrder,
            ] + $destination);

            // is_system is guarded — assigned, never mass-assigned.
            $created->is_system = true;
            $created->save();
        }
    }

    /**
     * The route if it exists, otherwise the homepage section.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function destinationFor(array $item): array
    {
        if (Route::has($item['route'])) {
            return ['link_type' => 'route', 'route_name' => $item['route'], 'url' => null];
        }

        // No route and no anchor means there is nowhere to send anyone yet;
        // the row is kept (so its label and order survive) but it renders
        // nothing until one of the two exists.
        return [
            'link_type' => isset($item['anchor']) ? 'url' : 'none',
            'route_name' => null,
            'url' => $item['anchor'] ?? null,
        ];
    }

    /**
     * Every route here is present in MenuItem::ROUTE_WHITELIST, which is
     * what stops a menu row resolving to a route that needs a bound model
     * and 500-ing the navbar on every page.
     *
     * `anchor` is the homepage section that holds this content until the
     * dedicated page is built. Fragment-only by design — MenuItem::href()
     * resolves it against the current locale's homepage, so it works from
     * /en as well as /.
     *
     * THE ORDER OF THIS ARRAY IS THE ORDER OF THE NAVBAR — sort_order is
     * the array index. It runs from what most visitors want (home, then
     * what we do, then what it costs) to what fewest do (who we are, what
     * we write), ending on contact.
     *
     * THERE IS NO BOOKING ENTRY, deliberately. "احجز استشارة" is the
     * orange CTA button in x-public.navbar, which sits after this list in
     * both the desktop bar and the mobile drawer and points at the same
     * /book page. A menu row for it as well put the same call to action in
     * the bar twice, competing with itself — the one thing a CTA must not
     * do. See the 2026_08_16_000200 data migration, which removes the row
     * from installs that already have it.
     *
     * @return array<int, array{key: string, route: string, anchor?: string, label: array<string, string>}>
     */
    private function items(): array
    {
        return [
            ['key' => 'nav-home', 'route' => 'home', 'label' => ['ar' => 'الرئيسية', 'en' => 'Home']],
            ['key' => 'nav-services', 'route' => 'services.index', 'anchor' => '#services', 'label' => ['ar' => 'خدماتنا', 'en' => 'Services']],
            ['key' => 'nav-activities', 'route' => 'pages.activities', 'anchor' => '#activities', 'label' => ['ar' => 'الأنشطة', 'en' => 'Activities']],
            ['key' => 'nav-packages', 'route' => 'packages.index', 'anchor' => '#packages', 'label' => ['ar' => 'الباقات', 'en' => 'Packages']],
            ['key' => 'nav-estimator', 'route' => 'estimator', 'anchor' => '#estimator', 'label' => ['ar' => 'حاسبة التكلفة', 'en' => 'Cost Estimator']],
            ['key' => 'nav-about', 'route' => 'pages.about', 'label' => ['ar' => 'من نحن', 'en' => 'About']],
            ['key' => 'nav-articles', 'route' => 'articles.index', 'anchor' => '#articles', 'label' => ['ar' => 'المدونة', 'en' => 'Blog']],
            // No anchor. The contact route is registered now, so this is a
            // route link; the fallback would have pointed at the homepage
            // booking section, which is the CTA's destination, not this
            // item's.
            //
            // Labelled to match the contact page's own title
            // (site.contact.title) rather than its short form, so the bar
            // and the page a click lands on say the same thing.
            ['key' => 'nav-contact', 'route' => 'contact', 'anchor' => null, 'label' => ['ar' => 'تواصل معنا', 'en' => 'Contact us']],
        ];
    }
}
