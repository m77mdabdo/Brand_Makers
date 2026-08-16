<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes the duplicate booking entry from the public navbar and puts the
 * remaining items into the intended reading order, on an EXISTING install.
 *
 * WHAT WAS WRONG. "احجز استشارة" appeared in the bar twice: once as an
 * ordinary menu row (nav-booking) and once as the orange CTA button that
 * x-public.navbar renders after the menu. Both went to the same place. A
 * call to action competing with a plain link for the same click is the one
 * thing a CTA must not do, and on the mobile drawer the two sat six lines
 * apart. The row goes; the button stays.
 *
 * The order was also an accident of the build sequence rather than a
 * decision — the blog sat above "من نحن" because the articles step shipped
 * before the about page did.
 *
 * WHY A MIGRATION AND NOT A RESEED. MenuItemSeeder creates rows it does
 * not already have and re-applies labels; it deliberately does NOT
 * re-apply sort_order and cannot delete anything, because hiding and
 * reordering the core items are legitimate admin choices (see its
 * docblock). So the seeder change alone reaches fresh installs only. This
 * carries the same change to a site already running.
 *
 * TWO DIFFERENT STANDARDS OF CARE, on purpose:
 *
 *   - The duplicate row is deleted unconditionally. It is a defect, it is
 *     visible on every page, and no admin arrangement makes having the
 *     same call to action twice correct.
 *
 *   - The reordering is applied only if the system items are still in the
 *     exact arrangement the old seeder installed. An admin who has already
 *     reordered the bar has expressed an intention, and silently
 *     overwriting it is precisely what the seeder refuses to do. Their
 *     arrangement is left alone; only the duplicate goes.
 *
 * Admin-created items (is_system = false) are ignored throughout: they are
 * neither part of the arrangement being compared nor reordered, so an
 * admin who added an entry of their own still gets the fix.
 *
 * down() is the mirror image — it restores the previous arrangement and
 * recreates the row, under the same "only if untouched" rule.
 */
return new class extends Migration
{
    /**
     * The arrangement this migration installs.
     *
     * Kept in step with MenuItemSeeder::items(), whose array order is its
     * sort_order. NavigationOrderTest asserts the two have not drifted.
     *
     * @var array<string, int>
     */
    private const ORDER = [
        'nav-home' => 0,
        'nav-services' => 1,
        'nav-packages' => 2,
        'nav-estimator' => 3,
        'nav-about' => 4,
        'nav-articles' => 5,
        'nav-contact' => 6,
    ];

    /**
     * The arrangement the seeder installed BEFORE this change — what an
     * untouched install looks like, and what down() restores.
     *
     * @var array<string, int>
     */
    private const PREVIOUS_ORDER = [
        'nav-home' => 0,
        'nav-services' => 1,
        'nav-packages' => 2,
        'nav-estimator' => 3,
        'nav-articles' => 4,
        'nav-about' => 5,
        'nav-booking' => 6,
        'nav-contact' => 7,
    ];

    /**
     * The contact item's rewording, old label => new label, per locale.
     *
     * The bar said "اتصل بنا" while the page it opens is titled "تواصل
     * معنا" (site.contact.title). Applied ONLY where the old wording is
     * still in place, so an admin who has already renamed the item keeps
     * their wording — unlike MenuItemSeeder, which re-applies labels
     * unconditionally because a reseed is a deliberate act and a migration
     * is not.
     *
     * @var array<string, array{from: string, to: string}>
     */
    private const CONTACT_LABEL = [
        'ar' => ['from' => 'اتصل بنا', 'to' => 'تواصل معنا'],
        'en' => ['from' => 'Contact', 'to' => 'Contact us'],
    ];

    /**
     * The row up() removes, in full, so down() can put it back as it was.
     *
     * @var array<string, mixed>
     */
    private const BOOKING_ITEM = [
        'key' => 'nav-booking',
        'label' => ['ar' => 'احجز استشارة', 'en' => 'Book a Consultation'],
        'link_type' => 'route',
        'route_name' => 'booking',
    ];

    public function up(): void
    {
        // Read the arrangement BEFORE the delete: the comparison includes
        // nav-booking, which is what makes "untouched" checkable at all.
        $untouched = $this->arrangementIs(self::PREVIOUS_ORDER);

        MenuItem::query()->topLevel()->where('key', self::BOOKING_ITEM['key'])->delete();

        $this->relabelContact('from', 'to');

        if ($untouched) {
            $this->apply(self::ORDER);
        }
    }

    public function down(): void
    {
        $this->relabelContact('to', 'from');

        if (! $this->arrangementIs(self::ORDER)) {
            // The bar has been rearranged since; putting the old numbers
            // back would scramble it rather than reverse this migration.
            return;
        }

        $this->restoreBookingItem();

        $this->apply(self::PREVIOUS_ORDER);
    }

    /**
     * Rewords the contact item, per locale, only where the expected
     * wording is still in place.
     */
    private function relabelContact(string $expected, string $replacement): void
    {
        $item = MenuItem::query()->topLevel()->where('key', 'nav-contact')->first();

        if (! $item) {
            return;
        }

        $changed = false;

        foreach (self::CONTACT_LABEL as $locale => $wording) {
            // false: the raw translation, with no fallback. Otherwise a
            // row with no English label reads as the Arabic one and would
            // never match — or worse, would be given an English label it
            // never had.
            if ($item->getTranslation('label', $locale, false) !== $wording[$expected]) {
                continue;
            }

            $item->setTranslation('label', $locale, $wording[$replacement]);
            $changed = true;
        }

        if ($changed) {
            $item->save();
        }
    }

    /**
     * Whether the system items are exactly this set of keys at exactly
     * these positions — no more, no fewer, none moved.
     *
     * @param  array<string, int>  $expected
     */
    private function arrangementIs(array $expected): bool
    {
        $current = MenuItem::query()
            ->topLevel()
            ->system()
            ->pluck('sort_order', 'key')
            ->map(fn ($order) => (int) $order)
            ->all();

        ksort($current);
        ksort($expected);

        return $current === $expected;
    }

    /**
     * @param  array<string, int>  $order
     */
    private function apply(array $order): void
    {
        foreach ($order as $key => $sortOrder) {
            MenuItem::query()
                ->topLevel()
                ->where('key', $key)
                ->update(['sort_order' => $sortOrder]);
        }
    }

    private function restoreBookingItem(): void
    {
        if (MenuItem::query()->topLevel()->where('key', self::BOOKING_ITEM['key'])->exists()) {
            return;
        }

        $item = MenuItem::create(self::BOOKING_ITEM + [
            'target' => '_self',
            'parent_id' => null,
            'is_visible' => true,
            'sort_order' => self::PREVIOUS_ORDER[self::BOOKING_ITEM['key']],
        ]);

        // is_system is guarded — assigned, never mass-assigned.
        $item->is_system = true;
        $item->save();
    }
};
