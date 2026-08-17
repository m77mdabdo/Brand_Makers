<?php

namespace Tests\Feature\Dashboard;

use App\Models\EstimatorItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The price list — the one place a figure on the public estimator can come
 * from.
 */
class EstimatorItemsCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'key' => 'motion-reel',
            'label' => ['ar' => 'ريل متحرك', 'en' => 'Motion reel'],
            'group' => 'content',
            'price' => '4500.00',
            'is_active' => '1',
            'sort_order' => '3',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Gating
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{string, bool}>
     */
    public static function guardedScreens(): array
    {
        return [
            'index' => ['dashboard.estimator-items.index', false],
            'create' => ['dashboard.estimator-items.create', false],
            'edit' => ['dashboard.estimator-items.edit', true],
        ];
    }

    /**
     * One test per screen rather than a loop: actingAs() authenticates for
     * the rest of the test, so a loop would still be signed in on its
     * second pass and the guest assertion would pass vacuously.
     */
    #[DataProvider('guardedScreens')]
    public function test_the_screen_redirects_a_guest(string $route, bool $needsItem): void
    {
        $url = $needsItem ? route($route, EstimatorItem::factory()->create()) : route($route);

        $this->get($url)->assertRedirect(route('login'));
    }

    #[DataProvider('guardedScreens')]
    public function test_the_screen_is_forbidden_to_a_non_admin(string $route, bool $needsItem): void
    {
        $url = $needsItem ? route($route, EstimatorItem::factory()->create()) : route($route);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    #[DataProvider('guardedScreens')]
    public function test_an_admin_reaches_the_screen(string $route, bool $needsItem): void
    {
        $url = $needsItem ? route($route, EstimatorItem::factory()->create()) : route($route);

        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    public function test_a_non_admin_cannot_write(): void
    {
        $item = EstimatorItem::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('dashboard.estimator-items.store'), $this->payload())
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->delete(route('dashboard.estimator-items.destroy', $item))
            ->assertForbidden();

        $this->assertSame(1, EstimatorItem::count());
    }

    public function test_the_sidebar_links_to_the_section(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertSee(route('dashboard.estimator-items.index'), false);
    }

    // -----------------------------------------------------------------
    // Create
    // -----------------------------------------------------------------

    public function test_an_admin_can_add_a_priced_item(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-items.store'), $this->payload())
            ->assertRedirect(route('dashboard.estimator-items.index'));

        $item = EstimatorItem::sole();

        $this->assertSame('motion-reel', $item->key);
        $this->assertSame('ريل متحرك', $item->getTranslation('label', 'ar'));
        $this->assertSame('Motion reel', $item->getTranslation('label', 'en'));
        $this->assertSame('content', $item->group);
        $this->assertSame('4500.00', $item->price);
        $this->assertTrue($item->is_active);
        $this->assertSame(3, $item->sort_order);
    }

    public function test_a_new_item_appears_on_the_public_estimator_immediately(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.estimator-items.store'), $this->payload());

        // No hardcoded price anywhere: what the studio enters here is what
        // a visitor is quoted.
        $this->get('/en/cost-estimator')
            ->assertSee('Motion reel', false)
            ->assertSee('4,500', false);
    }

    public function test_a_blank_key_is_generated_from_the_english_label(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.estimator-items.store'), $this->payload(['key' => '']));

        $this->assertSame('motion-reel', EstimatorItem::sole()->key);
    }

    public function test_a_generated_key_that_collides_gets_a_suffix(): void
    {
        EstimatorItem::factory()->create(['key' => 'motion-reel']);

        $this->actingAs($this->admin)->post(route('dashboard.estimator-items.store'), $this->payload(['key' => '']));

        // latest('id'), not where('id', '>', 1): MySQL does not roll back
        // its auto-increment counter between tests, so the pre-existing
        // row is not reliably id 1 and both rows would match.
        $this->assertSame('motion-reel-2', EstimatorItem::latest('id')->firstOrFail()->key);
    }

    public function test_a_typed_key_that_collides_is_reported_rather_than_renamed(): void
    {
        EstimatorItem::factory()->create(['key' => 'motion-reel']);

        // Silently renaming an identifier the admin typed would be worse
        // than telling them it is taken.
        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-items.store'), $this->payload())
            ->assertSessionHasErrors('key');

        $this->assertSame(1, EstimatorItem::count());
    }

    public function test_markup_in_a_label_is_stripped_on_write(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.estimator-items.store'), $this->payload([
            'label' => ['ar' => 'بند <script>alert(1)</script>', 'en' => 'Item <b>bold</b>'],
        ]));

        $item = EstimatorItem::sole();

        // A checkbox caption is never rich text.
        $this->assertStringNotContainsString('<script>', $item->getTranslation('label', 'ar'));
        $this->assertStringNotContainsString('<b>', $item->getTranslation('label', 'en'));
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
            'no Arabic label' => [['label' => ['ar' => '', 'en' => 'English only']], 'label.ar'],
            'no group' => [['group' => ''], 'group'],
            'a group with spaces' => [['group' => 'not a slug'], 'group'],
            'a non-numeric price' => [['price' => 'free'], 'price'],
            'a negative price' => [['price' => '-1'], 'price'],
            'a price past the column' => [['price' => '99999999999999'], 'price'],
            'a negative sort order' => [['sort_order' => '-1'], 'sort_order'],
            'a key with spaces' => [['key' => 'not a key'], 'key'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_an_invalid_payload_is_rejected(array $overrides, string $field): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-items.store'), $this->payload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, EstimatorItem::count());
    }

    /**
     * This asserted the opposite until price became nullable.
     *
     * The old rule — an active item must carry a price above zero —
     * existed only because there was no way to say "not priced yet"
     * except is_active = false. Now null says it, so an active line
     * without a price is a legitimate state: it renders as "على الطلب"
     * and turns the quote into a floor instead of adding nothing silently.
     */
    public function test_a_blank_price_saves_as_on_request_and_may_be_live(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-items.store'), $this->payload(['price' => '', 'is_active' => '1']))
            ->assertSessionHasNoErrors();

        $item = EstimatorItem::sole();

        // null, NOT 0.00 — "on request" is a different fact from free, and
        // coercing a cleared box to zero would say the wrong one.
        $this->assertNull($item->price);
        $this->assertTrue($item->is_active);
        $this->assertTrue($item->isPriceOnRequest());
    }

    public function test_an_explicit_zero_is_stored_as_free_rather_than_as_on_request(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-items.store'), $this->payload(['price' => '0', 'is_active' => '1']))
            ->assertSessionHasNoErrors();

        $item = EstimatorItem::sole();

        // Typing 0 is an answer: this line costs nothing. It must not be
        // confused with the blank box above.
        $this->assertSame('0.00', $item->price);
        $this->assertFalse($item->isPriceOnRequest());
    }

    public function test_an_item_priced_at_zero_may_be_saved_while_inactive(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-items.store'), $this->payload(['price' => '0', 'is_active' => '0']))
            ->assertSessionHasNoErrors();

        $item = EstimatorItem::sole();

        // Which is exactly the state the seeder ships the catalogue in.
        $this->assertSame('0.00', $item->price);
        $this->assertFalse($item->is_active);
    }

    public function test_an_english_only_label_is_rejected(): void
    {
        // Arabic-primary, matching every translatable field in this
        // project: a record with only English renders as a gap on the
        // default locale.
        $this->actingAs($this->admin)
            ->post(route('dashboard.estimator-items.store'), $this->payload(['label' => ['ar' => '', 'en' => 'English only']]))
            ->assertSessionHasErrors('label.ar');
    }

    // -----------------------------------------------------------------
    // Arbitrary keys
    // -----------------------------------------------------------------

    public function test_a_key_outside_the_definition_is_never_written(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.estimator-items.store'), $this->payload([
            'currency' => 'USD',
            'created_at' => '1999-01-01 00:00:00',
            'id' => 999,
        ]));

        $item = EstimatorItem::sole();

        // currency is deliberately not editable: the catalogue is
        // single-currency, and a mixed one cannot be summed.
        $this->assertSame('SAR', $item->currency);
        $this->assertNotSame(999, $item->id);
        $this->assertTrue($item->created_at->isToday());
    }

    // -----------------------------------------------------------------
    // Update
    // -----------------------------------------------------------------

    public function test_an_admin_can_reprice_an_item(): void
    {
        $item = EstimatorItem::factory()->create(['key' => 'logo', 'price' => 1000, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('dashboard.estimator-items.update', $item), $this->payload(['price' => '7250.50', 'key' => null]))
            ->assertRedirect(route('dashboard.estimator-items.index'));

        $this->assertSame('7250.50', $item->fresh()->price);
    }

    public function test_the_key_is_immutable(): void
    {
        $item = EstimatorItem::factory()->create(['key' => 'logo']);

        $this->actingAs($this->admin)->put(route('dashboard.estimator-items.update', $item), $this->payload(['key' => 'renamed']));

        // The seeder matches on it to stay idempotent, and stored estimate
        // snapshots reference it. UpdateEstimatorItemRequest has no rule
        // for it, so validated() drops it before the service ever sees it.
        $this->assertSame('logo', $item->fresh()->key);
    }

    public function test_deactivating_an_item_removes_it_from_the_public_estimator(): void
    {
        $item = EstimatorItem::factory()->create([
            'key' => 'logo', 'price' => 6000, 'is_active' => true,
            'label' => ['ar' => 'شعار', 'en' => 'Logo Item'],
        ]);

        $this->get('/en/cost-estimator')->assertSee('Logo Item', false);

        $this->actingAs($this->admin)->put(route('dashboard.estimator-items.update', $item), [
            'label' => ['ar' => 'شعار', 'en' => 'Logo Item'],
            'group' => 'identity',
            'price' => '6000',
            'is_active' => '0',
            'sort_order' => '0',
        ]);

        $this->get('/en/cost-estimator')->assertDontSee('Logo Item', false);
    }

    // -----------------------------------------------------------------
    // Delete
    // -----------------------------------------------------------------

    public function test_an_admin_can_delete_an_item(): void
    {
        $item = EstimatorItem::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('dashboard.estimator-items.destroy', $item))
            ->assertRedirect(route('dashboard.estimator-items.index'));

        $this->assertDatabaseMissing('estimator_items', ['id' => $item->id]);
    }

    // -----------------------------------------------------------------
    // The index tells the truth about the public page
    // -----------------------------------------------------------------

    public function test_the_index_warns_when_nothing_is_active(): void
    {
        EstimatorItem::factory()->inactive()->create(['price' => 0]);

        // The state of a fresh install. Saying so is the difference
        // between "the estimator is broken" and "nobody has priced it".
        $this->actingAs($this->admin)
            ->get(route('dashboard.estimator-items.index'))
            // Escaped, not raw: the notice quotes the public page's
            // "pricing in preparation" wording, and Blade renders those
            // quotation marks as &quot;.
            ->assertSee(__('dashboard.estimator_items.none_active'));
    }

    public function test_the_warning_disappears_once_something_is_priced_and_active(): void
    {
        EstimatorItem::factory()->create(['price' => 4500, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.estimator-items.index'))
            ->assertDontSee(__('dashboard.estimator_items.none_active'));
    }

    public function test_an_unpriced_row_is_labelled_rather_than_shown_as_zero(): void
    {
        EstimatorItem::factory()->inactive()->create(['price' => 0]);

        // Printing a bare 0.00 would read as free.
        $this->actingAs($this->admin)
            ->get(route('dashboard.estimator-items.index'))
            ->assertSee(__('dashboard.estimator_items.unpriced'), false);
    }

    // -----------------------------------------------------------------
    // Re-seeding never overwrites a real price
    // -----------------------------------------------------------------

    public function test_re_seeding_never_overwrites_a_price_an_admin_has_entered(): void
    {
        $this->seed();

        $item = EstimatorItem::where('key', 'feasibility-study')->sole();
        $item->update(['price' => 8500, 'is_active' => true]);

        $this->seed();

        $item->refresh();

        $this->assertSame('8500.00', $item->price);
        $this->assertTrue($item->is_active, 'Re-seeding must not switch the public estimator back off.');
    }
}
