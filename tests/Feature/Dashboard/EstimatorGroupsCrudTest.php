<?php

namespace Tests\Feature\Dashboard;

use App\Models\EstimatorGroup;
use App\Models\EstimatorItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The estimator's selectors, managed from the dashboard.
 *
 * A group is one dropdown on the public form. The point of this screen is
 * that the whole selector — its heading, its position, and whether it
 * accepts one answer or several — is data an admin owns, rather than the
 * half-and-half arrangement it replaced, where the slug was editable and
 * the label an admin actually saw was hardcoded in a lang file.
 */
class EstimatorGroupsCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'key' => 'extras',
            'label' => ['ar' => 'إضافات', 'en' => 'Extras'],
            'selection' => EstimatorGroup::SELECTION_MULTI,
            'sort_order' => 3,
            'is_active' => '1',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Gating
    // -----------------------------------------------------------------

    public function test_a_guest_cannot_reach_the_screen(): void
    {
        $this->get(route('dashboard.estimator-groups.index'))->assertRedirect(route('login'));
    }

    public function test_a_non_admin_cannot_reach_the_screen(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('dashboard.estimator-groups.index'))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------

    public function test_an_admin_can_create_a_group(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-groups.store'), $this->payload())
            ->assertRedirect(route('dashboard.estimator-groups.index'));

        $group = EstimatorGroup::sole();

        $this->assertSame('extras', $group->key);
        $this->assertSame('إضافات', $group->getTranslation('label', 'ar'));
        $this->assertSame('Extras', $group->getTranslation('label', 'en'));
        $this->assertSame(EstimatorGroup::SELECTION_MULTI, $group->selection);
        $this->assertTrue($group->is_active);
    }

    public function test_a_blank_key_is_generated_from_the_arabic_label(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-groups.store'), $this->payload(['key' => '', 'label' => ['ar' => 'خدمات إضافية']]))
            ->assertSessionHasNoErrors();

        // Str::slug transliterates rather than dropping Arabic, so this is
        // a real slug and not an empty string — asserted rather than
        // assumed, because a blank key would violate the unique index on
        // the NEXT group created the same way.
        $this->assertNotSame('', EstimatorGroup::sole()->key);
    }

    public function test_an_admin_can_edit_a_group(): void
    {
        $group = EstimatorGroup::factory()->create(['key' => 'extras', 'selection' => EstimatorGroup::SELECTION_MULTI]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.estimator-groups.update', $group), $this->payload([
                'label' => ['ar' => 'اسم جديد', 'en' => 'Renamed'],
                'selection' => EstimatorGroup::SELECTION_SINGLE,
            ]))
            ->assertRedirect(route('dashboard.estimator-groups.index'));

        $group->refresh();

        $this->assertSame('اسم جديد', $group->getTranslation('label', 'ar'));
        $this->assertSame(EstimatorGroup::SELECTION_SINGLE, $group->selection);
    }

    public function test_the_key_is_immutable_because_items_point_at_it(): void
    {
        $group = EstimatorGroup::factory()->create(['key' => 'extras']);
        EstimatorItem::factory()->create(['group' => 'extras']);

        $this->actingAs($this->admin)
            ->put(route('dashboard.estimator-groups.update', $group), $this->payload(['key' => 'renamed']))
            ->assertRedirect();

        // Renaming would orphan every item in the group from its heading
        // in one write, so the update request has no `key` rule at all and
        // validated() drops it.
        $this->assertSame('extras', $group->fresh()->key);
        $this->assertSame(1, EstimatorItem::where('group', 'extras')->count());
    }

    public function test_deleting_a_group_keeps_its_items(): void
    {
        $group = EstimatorGroup::factory()->create(['key' => 'extras']);
        EstimatorItem::factory()->create(['group' => 'extras', 'price' => 500, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->delete(route('dashboard.estimator-groups.destroy', $group))
            ->assertRedirect(route('dashboard.estimator-groups.index'));

        // The two are joined on a slug, not a foreign key, precisely so
        // that removing a heading cannot take priced rows with it.
        $this->assertSame(0, EstimatorGroup::count());
        $this->assertSame(1, EstimatorItem::where('group', 'extras')->count());
    }

    // -----------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'no Arabic label' => [['label' => ['ar' => '', 'en' => 'Extras']], 'label.ar'],
            'no selection' => [['selection' => ''], 'selection'],
            'an unknown selection' => [['selection' => 'sometimes'], 'selection'],
            'a key with spaces' => [['key' => 'not a slug'], 'key'],
            'a negative sort order' => [['sort_order' => '-1'], 'sort_order'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_an_invalid_payload_is_rejected(array $overrides, string $field): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-groups.store'), $this->payload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, EstimatorGroup::count());
    }

    public function test_a_duplicate_key_is_reported_rather_than_silently_renamed(): void
    {
        EstimatorGroup::factory()->create(['key' => 'extras']);

        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-groups.store'), $this->payload(['key' => 'extras']))
            ->assertSessionHasErrors('key');
    }

    public function test_a_key_outside_the_definition_is_never_written(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-groups.store'), $this->payload(['created_at' => '1999-01-01 00:00:00']))
            ->assertSessionHasNoErrors();

        $this->assertNotEquals('1999', EstimatorGroup::sole()->created_at->format('Y'));
    }

    public function test_markup_in_a_label_is_stripped_on_write(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-groups.store'), $this->payload([
                'label' => ['ar' => '<script>alert(1)</script>إضافات'],
            ]))
            ->assertSessionHasNoErrors();

        // The label is a selector heading, never rich text.
        $this->assertStringNotContainsString('<script>', EstimatorGroup::sole()->getTranslation('label', 'ar'));
    }

    // -----------------------------------------------------------------
    // What the public form does with it
    // -----------------------------------------------------------------

    public function test_a_new_group_renders_its_own_selector_on_the_public_estimator(): void
    {
        $this->seed();

        $group = EstimatorGroup::factory()->create([
            'key' => 'extras',
            'label' => ['ar' => 'إضافات المكتب', 'en' => 'Office extras'],
            'selection' => EstimatorGroup::SELECTION_MULTI,
        ]);

        EstimatorItem::factory()->create([
            'key' => 'desk',
            'group' => 'extras',
            'price' => 900,
            'is_active' => true,
            'label' => ['ar' => 'مكتب', 'en' => 'Desk'],
        ]);

        $this->get('/cost-estimator')
            ->assertOk()
            ->assertSee('إضافات المكتب', false)
            ->assertSee('مكتب', false);
    }

    public function test_a_group_with_no_active_items_renders_no_selector(): void
    {
        $this->seed();

        EstimatorGroup::factory()->create(['key' => 'empty-group', 'label' => ['ar' => 'مجموعة فارغة']]);

        // An empty dropdown is worse than no dropdown.
        $this->get('/cost-estimator')->assertOk()->assertDontSee('مجموعة فارغة', false);
    }

    public function test_an_inactive_group_renders_no_selector(): void
    {
        $this->seed();

        $group = EstimatorGroup::factory()->create([
            'key' => 'hidden-group',
            'label' => ['ar' => 'مجموعة مخفية'],
            'is_active' => false,
        ]);

        EstimatorItem::factory()->create(['key' => 'thing', 'group' => 'hidden-group', 'price' => 100, 'is_active' => true]);

        $this->get('/cost-estimator')->assertOk()->assertDontSee('مجموعة مخفية', false);
    }
}
