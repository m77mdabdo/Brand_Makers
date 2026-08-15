<?php

namespace Tests\Feature\Dashboard;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MenuManagerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A route name nothing will ever register. See the test that uses it.
     */
    private const UNREGISTERED_ROUTE = 'never.to.be.registered';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_manager_is_admin_only(): void
    {
        $this->get(route('dashboard.menu.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('dashboard.menu.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('dashboard.menu.index'))->assertOk();
    }

    public function test_an_admin_creates_an_item_at_the_end_of_its_level(): void
    {
        $before = MenuItem::topLevel()->max('sort_order');

        $this->actingAs($this->admin)->post(route('dashboard.menu.store'), [
            'label' => ['ar' => 'عنصر جديد', 'en' => 'New item'],
            'link_type' => 'route',
            'route_name' => 'home',
            'is_visible' => '1',
        ])->assertRedirect(route('dashboard.menu.index'));

        $item = MenuItem::latest('id')->firstOrFail();

        $this->assertSame('عنصر جديد', $item->getTranslation('label', 'ar'));
        $this->assertGreaterThan($before, $item->sort_order);
        // is_system is guarded, so a new item can never claim protection.
        $this->assertFalse($item->is_system);
    }

    // -----------------------------------------------------------------
    // The route whitelist — no 500-able links
    // -----------------------------------------------------------------

    public function test_a_route_outside_the_whitelist_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.menu.store'), [
                'label' => ['ar' => 'عنصر'],
                'link_type' => 'route',
                'route_name' => 'dashboard.services.destroy',
            ])
            ->assertSessionHasErrors('route_name');
    }

    public function test_a_route_needing_a_bound_model_is_not_even_offered(): void
    {
        // services.show would need a parameter, so route() would throw and
        // take down the navbar — and therefore every page on the site.
        $this->assertArrayNotHasKey('services.show', MenuItem::ROUTE_WHITELIST);
        $this->assertArrayNotHasKey('articles.show', MenuItem::ROUTE_WHITELIST);
    }

    public function test_every_whitelisted_route_is_parameter_free_when_it_exists(): void
    {
        foreach (array_keys(MenuItem::ROUTE_WHITELIST) as $name) {
            $route = Route::getRoutes()->getByName($name);

            if ($route === null) {
                continue;
            }

            $this->assertEmpty(
                $route->parameterNames(),
                "Whitelisted route [{$name}] takes parameters and would throw on route()."
            );
        }
    }

    public function test_an_item_pointing_at_an_unbuilt_route_renders_nothing_rather_than_erroring(): void
    {
        /*
         * A NAME THAT WILL NEVER BE REGISTERED, deliberately.
         *
         * This fixture has now gone stale twice — it named packages.index,
         * which got built, then orders.track, which got built too. The
         * behaviour under test is href() returning null for a route that
         * does not resolve, and that does not depend on the name being one
         * the whitelist happens to offer. A sentinel says the same thing
         * and cannot expire.
         */
        $this->assertFalse(Route::has(self::UNREGISTERED_ROUTE));

        $item = MenuItem::factory()->create(['link_type' => 'route', 'route_name' => self::UNREGISTERED_ROUTE]);

        $this->assertNull($item->href());
        $this->get('/')->assertOk();
    }

    // -----------------------------------------------------------------
    // Nesting
    // -----------------------------------------------------------------

    public function test_a_child_cannot_be_chosen_as_a_parent(): void
    {
        $parent = MenuItem::factory()->create();
        $child = MenuItem::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.menu.store'), [
                'label' => ['ar' => 'حفيد'],
                'link_type' => 'none',
                'parent_id' => $child->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_an_item_cannot_become_its_own_parent(): void
    {
        $item = MenuItem::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('dashboard.menu.update', $item), [
                'label' => ['ar' => 'عنصر'],
                'link_type' => 'none',
                'parent_id' => $item->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    // -----------------------------------------------------------------
    // Reorder
    // -----------------------------------------------------------------

    public function test_reorder_writes_positions_and_parents_in_one_pass(): void
    {
        $first = MenuItem::factory()->create(['sort_order' => 0]);
        $second = MenuItem::factory()->create(['sort_order' => 1]);

        $this->actingAs($this->admin)->post(route('dashboard.menu.reorder'), [
            'items' => [
                ['id' => $second->id, 'parent_id' => null],
                ['id' => $first->id, 'parent_id' => $second->id],
            ],
        ])->assertRedirect(route('dashboard.menu.index'));

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame($second->id, $first->fresh()->parent_id);
    }

    public function test_reorder_normalises_an_illegal_parent_instead_of_losing_the_whole_arrangement(): void
    {
        $parent = MenuItem::factory()->create();
        $child = MenuItem::factory()->create(['parent_id' => $parent->id]);
        $other = MenuItem::factory()->create();

        // Parenting to a CHILD would create a grandchild the navbar never
        // renders. One bad row must not reject the admin's whole layout.
        $this->actingAs($this->admin)->post(route('dashboard.menu.reorder'), [
            'items' => [
                ['id' => $other->id, 'parent_id' => $child->id],
            ],
        ])->assertRedirect();

        $this->assertNull($other->fresh()->parent_id);
    }

    public function test_reorder_pulls_grandchildren_back_up(): void
    {
        $parent = MenuItem::factory()->create();
        $child = MenuItem::factory()->create(['parent_id' => $parent->id]);
        $grandchild = MenuItem::factory()->create(['parent_id' => $child->id]);

        $this->actingAs($this->admin)->post(route('dashboard.menu.reorder'), [
            'items' => [['id' => $parent->id, 'parent_id' => null]],
        ]);

        $this->assertNull($grandchild->fresh()->parent_id);
    }

    // -----------------------------------------------------------------
    // Visibility, deletion, orphans
    // -----------------------------------------------------------------

    public function test_visibility_toggles_and_a_hidden_item_leaves_the_public_navbar(): void
    {
        $item = MenuItem::factory()->create([
            'label' => ['ar' => 'عنصر ظاهر', 'en' => 'Visible item'],
            'link_type' => 'route',
            'route_name' => 'home',
        ]);

        $this->get('/')->assertSee('عنصر ظاهر', false);

        $this->actingAs($this->admin)->patch(route('dashboard.menu.visibility', $item))->assertRedirect();

        $this->assertFalse($item->fresh()->is_visible);
        $this->get('/')->assertDontSee('عنصر ظاهر', false);
    }

    public function test_deleting_a_parent_reparents_its_children_rather_than_orphaning_them(): void
    {
        $parent = MenuItem::factory()->create();
        $child = MenuItem::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->admin)->delete(route('dashboard.menu.destroy', $parent))->assertRedirect();

        $this->assertDatabaseMissing('menu_items', ['id' => $parent->id]);
        // The child survives as a normal top-level item, not deleted and
        // not pointing at a row that no longer exists.
        $this->assertDatabaseHas('menu_items', ['id' => $child->id]);
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_a_system_item_cannot_be_deleted_only_hidden(): void
    {
        $system = MenuItem::system()->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('dashboard.menu.destroy', $system))
            ->assertRedirect(route('dashboard.menu.index'))
            ->assertSessionHas('status', __('dashboard.flash.system_item_protected'));

        $this->assertDatabaseHas('menu_items', ['id' => $system->id]);

        // Hiding it IS allowed — that is the supported way to take a core
        // item out of the navbar.
        $this->actingAs($this->admin)->patch(route('dashboard.menu.visibility', $system));
        $this->assertFalse($system->fresh()->is_visible);
    }

    public function test_the_public_navbar_survives_an_empty_menu(): void
    {
        MenuItem::query()->delete();

        $this->get('/')->assertOk();
        $this->get('/en')->assertOk();
        $this->actingAs($this->admin)->get(route('dashboard.menu.index'))->assertOk();
    }

    public function test_switching_link_type_clears_the_field_the_new_type_does_not_use(): void
    {
        $item = MenuItem::factory()->url('https://example.test')->create();

        $this->actingAs($this->admin)->put(route('dashboard.menu.update', $item), [
            'label' => ['ar' => 'عنصر'],
            'link_type' => 'route',
            'route_name' => 'home',
        ]);

        // A stale url must not resurface if the type is switched back.
        $this->assertNull($item->fresh()->url);
        $this->assertSame('home', $item->fresh()->route_name);
    }
}
