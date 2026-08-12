<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

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
            $existing = MenuItem::where('key', $item['key'])->first();

            if ($existing) {
                $existing->update([
                    'label' => $item['label'],
                    'link_type' => 'route',
                    'route_name' => $item['route'],
                    'url' => null,
                ]);

                $existing->is_system = true;
                $existing->save();

                continue;
            }

            $created = MenuItem::create([
                'key' => $item['key'],
                'label' => $item['label'],
                'link_type' => 'route',
                'route_name' => $item['route'],
                'url' => null,
                'target' => '_self',
                'parent_id' => null,
                'is_visible' => true,
                'sort_order' => $sortOrder,
            ]);

            // is_system is guarded — assigned, never mass-assigned.
            $created->is_system = true;
            $created->save();
        }
    }

    /**
     * Every route here is present in MenuItem::ROUTE_WHITELIST, which is
     * what stops a menu row resolving to a route that needs a bound model
     * and 500-ing the navbar on every page.
     *
     * @return array<int, array{key: string, route: string, label: array<string, string>}>
     */
    private function items(): array
    {
        return [
            ['key' => 'nav-home', 'route' => 'home', 'label' => ['ar' => 'الرئيسية', 'en' => 'Home']],
            ['key' => 'nav-services', 'route' => 'services.index', 'label' => ['ar' => 'خدماتنا', 'en' => 'Services']],
            ['key' => 'nav-packages', 'route' => 'packages.index', 'label' => ['ar' => 'الباقات', 'en' => 'Packages']],
            ['key' => 'nav-estimator', 'route' => 'estimator', 'label' => ['ar' => 'حاسبة التكلفة', 'en' => 'Cost Estimator']],
            ['key' => 'nav-articles', 'route' => 'articles.index', 'label' => ['ar' => 'المدونة', 'en' => 'Blog']],
            ['key' => 'nav-about', 'route' => 'pages.about', 'label' => ['ar' => 'من نحن', 'en' => 'About']],
            ['key' => 'nav-booking', 'route' => 'booking', 'label' => ['ar' => 'احجز استشارة', 'en' => 'Book a Consultation']],
            ['key' => 'nav-contact', 'route' => 'contact', 'label' => ['ar' => 'اتصل بنا', 'en' => 'Contact']],
        ];
    }
}
