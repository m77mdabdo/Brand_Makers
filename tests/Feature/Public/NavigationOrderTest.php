<?php

namespace Tests\Feature\Public;

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * What the navbar contains, once each, and in what order.
 *
 * Separate from NavigationTest, which covers the RULES of the menu system
 * (resolution, active state, dropdowns, link safety). This file covers the
 * CONTENT the site actually ships: the sequence, and the invariant that no
 * call to action appears twice.
 *
 * "Once" is asserted PER SURFACE, not per page. The desktop bar and the
 * mobile drawer both render the menu — from the same component, which is
 * the point — so every label legitimately appears twice in the header
 * markup. What must never happen is a label appearing twice inside one of
 * them.
 */
class NavigationOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The intended reading order: what most visitors want first, ending on
     * contact, with the booking CTA after the menu rather than in it.
     *
     * @var list<string>
     */
    private const EXPECTED_ORDER = [
        'nav-home',
        'nav-services',
        'nav-packages',
        'nav-estimator',
        'nav-about',
        'nav-articles',
        'nav-contact',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function locales(): array
    {
        return [
            'Arabic' => ['ar', '/'],
            'English' => ['en', '/en'],
        ];
    }

    // -----------------------------------------------------------------
    // Extracting the two surfaces
    // -----------------------------------------------------------------

    private function header(string $url): string
    {
        preg_match('/<header\b.*?<\/header>/s', $this->get($url)->assertOk()->getContent(), $matches);

        $this->assertNotEmpty($matches, "The page at {$url} rendered no <header>.");

        return $matches[0];
    }

    /** The desktop bar's menu, and nothing around it. */
    private function desktopNav(string $url): string
    {
        preg_match('/<nav class="hidden.*?<\/nav>/s', $this->header($url), $matches);

        $this->assertNotEmpty($matches, "The page at {$url} rendered no desktop navigation.");

        return $matches[0];
    }

    /** The mobile drawer's menu, and nothing around it. */
    private function drawerNav(string $url): string
    {
        preg_match('/<nav class="space-y-1.*?<\/nav>/s', $this->header($url), $matches);

        $this->assertNotEmpty($matches, "The page at {$url} rendered no drawer navigation.");

        return $matches[0];
    }

    /**
     * The labels the menu is expected to render, in the current locale.
     *
     * Read from the database rather than hardcoded, so this counts what
     * the site ships rather than what this file believes it ships.
     *
     * @return list<string>
     */
    private function menuLabels(string $locale): array
    {
        return MenuItem::topLevel()->visible()->ordered()->get()
            ->filter(fn (MenuItem $item) => $item->href() !== null || $item->visibleChildren->isNotEmpty())
            ->map(fn (MenuItem $item) => $item->getTranslation('label', $locale))
            ->values()
            ->all();
    }

    // -----------------------------------------------------------------
    // Each item exactly once, in both locales
    // -----------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_each_menu_item_renders_exactly_once_in_the_desktop_bar(string $locale, string $url): void
    {
        $nav = $this->desktopNav($url);
        $labels = $this->menuLabels($locale);

        $this->assertCount(count(self::EXPECTED_ORDER), $labels);

        foreach ($labels as $label) {
            $this->assertSame(
                1,
                substr_count($nav, $label),
                "\"{$label}\" appears ".substr_count($nav, $label)." times in the desktop navbar at {$url}."
            );
        }
    }

    #[DataProvider('locales')]
    public function test_each_menu_item_renders_exactly_once_in_the_mobile_drawer(string $locale, string $url): void
    {
        $nav = $this->drawerNav($url);
        $labels = $this->menuLabels($locale);

        // The count as well as the per-label tally: a duplicate whose
        // wording differs slightly from the CTA's ("Book a Consultation"
        // vs "Book a consultation") passes every substring check and
        // still shows up as an eighth row.
        $this->assertCount(count(self::EXPECTED_ORDER), $labels);
        $this->assertSame(
            count(self::EXPECTED_ORDER),
            preg_match_all('/<a\b/', $nav),
            'The mobile drawer rendered a different number of links than the menu holds.'
        );

        foreach ($labels as $label) {
            $this->assertSame(
                1,
                substr_count($nav, $label),
                "\"{$label}\" appears ".substr_count($nav, $label)." times in the mobile drawer at {$url}."
            );
        }
    }

    #[DataProvider('locales')]
    public function test_the_menu_renders_in_the_intended_order_on_both_surfaces(string $locale, string $url): void
    {
        $labels = $this->menuLabels($locale);

        foreach ([$this->desktopNav($url), $this->drawerNav($url)] as $nav) {
            $positions = array_map(fn (string $label) => strpos($nav, $label), $labels);

            $sorted = $positions;
            sort($sorted);

            $this->assertSame($sorted, $positions, 'The navbar rendered its items out of order at '.$url);
        }
    }

    // -----------------------------------------------------------------
    // The booking CTA — button only, never also a menu row
    // -----------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_the_booking_ask_appears_once_per_surface_and_never_as_a_menu_row(string $locale, string $url): void
    {
        $this->app->setLocale($locale);
        $cta = __('site.nav.book_consultation', [], $locale);

        // Not in the menu on either surface …
        $this->assertStringNotContainsString($cta, $this->desktopNav($url));
        $this->assertStringNotContainsString($cta, $this->drawerNav($url));

        // … and exactly twice in the bar as a whole: the desktop button
        // and the drawer button, one per surface.
        $this->assertSame(2, substr_count($this->header($url), $cta));
    }

    #[DataProvider('locales')]
    public function test_no_menu_row_points_at_the_booking_page(string $locale, string $url): void
    {
        $this->assertSame(
            0,
            MenuItem::topLevel()->visible()->where('route_name', 'booking')->count(),
            'The booking CTA is a button; a menu row for it duplicates the same ask.'
        );

        foreach ([$this->desktopNav($url), $this->drawerNav($url)] as $nav) {
            $this->assertStringNotContainsString('#booking', $nav);
        }
    }

    #[DataProvider('locales')]
    public function test_the_cta_links_to_the_booking_page_in_the_current_locale(string $locale, string $url): void
    {
        $header = $this->header($url);
        $expected = $locale === 'ar' ? '/book' : '/en/book';

        preg_match_all('/<a[^>]+href="([^"]*)"[^>]*class="btn btn-primary/s', $header, $matches);

        $this->assertCount(2, $matches[1], 'Expected one CTA button per surface.');

        foreach ($matches[1] as $href) {
            $this->assertSame($expected, parse_url($href, PHP_URL_PATH));
        }

        // A fragment would have done nothing on the thirty-odd pages that
        // are not the homepage, and the bar renders on all of them.
        $this->assertStringNotContainsString('href="#booking"', $header);
    }

    #[DataProvider('locales')]
    public function test_the_cta_marks_itself_current_on_the_booking_page_and_nowhere_else(string $locale, string $url): void
    {
        $booking = $locale === 'ar' ? route('booking') : route('booking.en');

        // One per surface, and nothing in the menu — no row points there.
        $this->assertSame(2, substr_count($this->header($booking), 'aria-current="page"'));
        $this->assertSame(0, substr_count($this->desktopNav($booking), 'aria-current="page"'));

        // And it does not follow the visitor around: on any other page the
        // two markers belong to that page's own menu row.
        $this->assertStringNotContainsString('aria-current', $this->ctaMarkup($url));
    }

    /** The desktop CTA's opening tag. */
    private function ctaMarkup(string $url): string
    {
        preg_match('/<a[^>]*class="btn btn-primary[^>]*>/s', $this->header($url), $matches);

        $this->assertNotEmpty($matches, "No CTA button rendered at {$url}.");

        return $matches[0];
    }

    // -----------------------------------------------------------------
    // The stored arrangement
    // -----------------------------------------------------------------

    public function test_a_fresh_install_seeds_the_intended_sequence(): void
    {
        $this->assertSame(
            self::EXPECTED_ORDER,
            MenuItem::topLevel()->system()->ordered()->pluck('key')->all()
        );
    }

    public function test_no_two_menu_items_share_a_key_or_a_destination(): void
    {
        $items = MenuItem::topLevel()->visible()->get();

        $keys = $items->pluck('key')->filter()->values();
        $this->assertSame($keys->unique()->count(), $keys->count(), 'Two menu items share a key.');

        // Two rows pointing at one page is the defect this whole change
        // was about, in its general form.
        $destinations = $items->map(fn (MenuItem $item) => $item->href())->filter()->values();
        $this->assertSame(
            $destinations->unique()->count(),
            $destinations->count(),
            'Two navbar items point at the same destination: '.$destinations->duplicates()->implode(', ')
        );
    }

    // -----------------------------------------------------------------
    // The data migration that carries this to an existing install
    // -----------------------------------------------------------------

    private function migration(): object
    {
        return require database_path('migrations/2026_08_16_000200_realign_public_navigation.php');
    }

    /**
     * @return array<string, mixed>
     */
    private function migrationConstant(string $name): mixed
    {
        return (new ReflectionClass($this->migration()))->getConstant($name);
    }

    /**
     * Puts the database back the way the OLD seeder left it: the booking
     * row present, and the previous sort order.
     */
    private function restorePreMigrationState(): void
    {
        /** @var array<string, int> $previous */
        $previous = $this->migrationConstant('PREVIOUS_ORDER');

        $booking = MenuItem::create([
            'key' => 'nav-booking',
            'label' => ['ar' => 'احجز استشارة', 'en' => 'Book a Consultation'],
            'link_type' => 'route',
            'route_name' => 'booking',
            'target' => '_self',
            'parent_id' => null,
            'is_visible' => true,
            'sort_order' => $previous['nav-booking'],
        ]);

        $booking->is_system = true;
        $booking->save();

        foreach ($previous as $key => $sortOrder) {
            MenuItem::topLevel()->where('key', $key)->update(['sort_order' => $sortOrder]);
        }

        $contact = MenuItem::topLevel()->where('key', 'nav-contact')->firstOrFail();
        $contact->setTranslations('label', ['ar' => 'اتصل بنا', 'en' => 'Contact']);
        $contact->save();
    }

    private function contactLabel(string $locale): ?string
    {
        return MenuItem::topLevel()->where('key', 'nav-contact')->firstOrFail()
            ->getTranslation('label', $locale, false);
    }

    public function test_the_seeder_and_the_migration_agree_on_the_order(): void
    {
        // Two places encode the sequence — the seeder for fresh installs,
        // the migration for existing ones. They drift silently otherwise.
        $this->assertSame(
            self::EXPECTED_ORDER,
            array_keys($this->migrationConstant('ORDER'))
        );

        $this->assertSame(
            array_values($this->migrationConstant('ORDER')),
            range(0, count(self::EXPECTED_ORDER) - 1),
            'sort_order must be contiguous from zero, as the seeder produces it.'
        );
    }

    public function test_the_migration_removes_the_duplicate_and_reorders_an_untouched_install(): void
    {
        $this->restorePreMigrationState();

        $this->assertTrue(MenuItem::where('key', 'nav-booking')->exists());

        $this->migration()->up();

        $this->assertFalse(
            MenuItem::where('key', 'nav-booking')->exists(),
            'The duplicate booking row survived the migration.'
        );

        $this->assertSame(
            self::EXPECTED_ORDER,
            MenuItem::topLevel()->system()->ordered()->pluck('key')->all()
        );

        // The bar and the page it opens now say the same thing.
        $this->assertSame('تواصل معنا', $this->contactLabel('ar'));
        $this->assertSame('Contact us', $this->contactLabel('en'));
    }

    public function test_the_migration_leaves_a_contact_label_an_admin_has_already_renamed(): void
    {
        $this->restorePreMigrationState();

        $contact = MenuItem::topLevel()->where('key', 'nav-contact')->firstOrFail();
        $contact->setTranslations('label', ['ar' => 'كلّمنا', 'en' => 'Talk to us']);
        $contact->save();

        $this->migration()->up();

        // Unconditional relabelling here would discard a rename the admin
        // made deliberately — a reseed may do that, a migration may not.
        $this->assertSame('كلّمنا', $this->contactLabel('ar'));
        $this->assertSame('Talk to us', $this->contactLabel('en'));
    }

    public function test_the_migration_removes_the_duplicate_but_respects_an_admin_reordering(): void
    {
        $this->restorePreMigrationState();

        // An admin has moved the blog to the front. That is an intention,
        // not a defect, and the seeder deliberately never overwrites it.
        MenuItem::where('key', 'nav-articles')->update(['sort_order' => -1]);

        $this->migration()->up();

        $this->assertFalse(MenuItem::where('key', 'nav-booking')->exists(), 'The duplicate must go regardless.');

        $this->assertSame(
            'nav-articles',
            MenuItem::topLevel()->system()->ordered()->first()->key,
            "The admin's ordering was overwritten."
        );
    }

    public function test_an_admin_added_item_does_not_block_the_reordering(): void
    {
        $this->restorePreMigrationState();

        // is_system = false, so it is not part of the arrangement being
        // compared — an admin with an extra entry still gets the fix.
        MenuItem::factory()->route('faqs.index')->create(['sort_order' => 99]);

        $this->migration()->up();

        $this->assertFalse(MenuItem::where('key', 'nav-booking')->exists());
        $this->assertSame(
            self::EXPECTED_ORDER,
            MenuItem::topLevel()->system()->ordered()->pluck('key')->all()
        );
    }

    public function test_the_migration_is_idempotent(): void
    {
        $this->restorePreMigrationState();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(
            self::EXPECTED_ORDER,
            MenuItem::topLevel()->system()->ordered()->pluck('key')->all()
        );
    }

    public function test_the_migration_reverses_itself(): void
    {
        /** @var array<string, int> $previous */
        $previous = $this->migrationConstant('PREVIOUS_ORDER');

        $migration = $this->migration();

        $migration->down();

        $this->assertSame(
            array_keys($previous),
            MenuItem::topLevel()->system()->ordered()->pluck('key')->all(),
            'down() did not restore the previous arrangement.'
        );

        $this->assertSame('اتصل بنا', $this->contactLabel('ar'));
        $this->assertSame('Contact', $this->contactLabel('en'));

        // And forward again, so the pair is a genuine round trip rather
        // than two half-working halves.
        $migration->up();

        $this->assertSame(
            self::EXPECTED_ORDER,
            MenuItem::topLevel()->system()->ordered()->pluck('key')->all()
        );

        $this->assertSame('تواصل معنا', $this->contactLabel('ar'));
        $this->assertSame('Contact us', $this->contactLabel('en'));
    }

    public function test_the_migration_is_a_no_op_on_a_database_with_no_menu(): void
    {
        MenuItem::query()->delete();

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(0, MenuItem::query()->count());
    }
}
