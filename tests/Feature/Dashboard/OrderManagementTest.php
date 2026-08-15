<?php

namespace Tests\Feature\Dashboard;

use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Package;
use App\Models\User;
use App\Services\Orders\OrderLookupService;
use App\Services\Orders\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderManagementTest extends TestCase
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
            'client_name' => 'Noura Client',
            'client_email' => 'noura@example.test',
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
            'index' => ['dashboard.orders.index', false],
            'create' => ['dashboard.orders.create', false],
            'show' => ['dashboard.orders.show', true],
            'edit' => ['dashboard.orders.edit', true],
        ];
    }

    #[DataProvider('guardedScreens')]
    public function test_the_screen_redirects_a_guest(string $route, bool $needsOrder): void
    {
        $url = $needsOrder ? route($route, Order::factory()->create()) : route($route);

        $this->get($url)->assertRedirect(route('login'));
    }

    #[DataProvider('guardedScreens')]
    public function test_the_screen_is_forbidden_to_a_non_admin(string $route, bool $needsOrder): void
    {
        $url = $needsOrder ? route($route, Order::factory()->create()) : route($route);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    #[DataProvider('guardedScreens')]
    public function test_an_admin_reaches_the_screen(string $route, bool $needsOrder): void
    {
        $url = $needsOrder ? route($route, Order::factory()->create()) : route($route);

        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    public function test_a_non_admin_cannot_create_or_move_an_order(): void
    {
        $order = Order::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('dashboard.orders.store'), $this->payload())
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->patch(route('dashboard.orders.status', $order), ['status' => Order::STATUS_COMPLETED])
            ->assertForbidden();

        $this->assertSame(0, Order::where('client_name', 'Noura Client')->count());
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_the_sidebar_links_to_the_section(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertSee(route('dashboard.orders.index'), false);
    }

    // -----------------------------------------------------------------
    // Creation, and the code shown once
    // -----------------------------------------------------------------

    public function test_creating_an_order_generates_a_reference_and_an_opening_timeline_entry(): void
    {
        $package = Package::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('dashboard.orders.store'), $this->payload([
                'package_id' => $package->id,
                'lead_id' => $lead->id,
                'total' => '18000',
                'note' => ['ar' => 'استلمنا طلبك'],
            ]))
            ->assertRedirect();

        $order = Order::sole();

        $this->assertMatchesRegularExpression('/^CM-\d{4}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/', $order->reference);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(0, $order->progress);
        $this->assertSame($package->id, $order->package_id);
        $this->assertSame($lead->id, $order->lead_id);

        // An order whose portal shows an empty timeline reads as broken.
        $this->assertCount(1, $order->events);
        $this->assertSame('استلمنا طلبك', $order->events->first()->note);
    }

    public function test_the_tracking_code_is_shown_once_and_only_its_hash_is_stored(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('dashboard.orders.store'), $this->payload())
            ->assertRedirect();

        $code = session('issued_tracking_code');
        $order = Order::sole();

        // A real 256-bit code, shown to the admin…
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $code);
        $this->actingAs($this->admin)->get($response->headers->get('Location'))
            ->assertSee($code, false)
            ->assertSee(__('dashboard.orders.code_shown_once'), false);

        // …and NOT stored. Only its digest is.
        $this->assertSame(hash('sha256', $code), $order->tracking_code_hash);
        $this->assertStringNotContainsString($code, json_encode($order->getAttributes()));
    }

    public function test_the_code_is_never_readable_again(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.orders.store'), $this->payload());

        $code = session('issued_tracking_code');
        $order = Order::sole();

        // The redirect target shows it — that is the one chance.
        $this->actingAs($this->admin)
            ->get(route('dashboard.orders.show', $order))
            ->assertSee($code, false);

        // A SECOND visit does not. The value was flashed, so it survived
        // exactly one request, and there is nothing in the database that
        // could reproduce it.
        $this->actingAs($this->admin)
            ->get(route('dashboard.orders.show', $order))
            ->assertDontSee($code, false)
            ->assertSee(__('dashboard.orders.code_stored_hint'), false);

        // And a model read back from the database has no plaintext.
        $this->assertNull(Order::find($order->id)->trackingCode());
    }

    public function test_the_hash_is_never_serialised(): void
    {
        $order = Order::factory()->create();

        // It is not the code, but it is the only thing standing between a
        // leaked API response and every order in the table.
        $this->assertArrayNotHasKey('tracking_code_hash', $order->toArray());
        $this->assertStringNotContainsString('tracking_code_hash', $order->toJson());
    }

    public function test_the_issued_code_actually_opens_the_portal(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.orders.store'), $this->payload());

        $code = session('issued_tracking_code');
        $order = Order::sole();

        // The end-to-end property: what the admin hands the client works.
        $this->assertNotNull(app(OrderLookupService::class)->find($order->reference, $code));
    }

    public function test_reissuing_a_code_invalidates_the_old_one(): void
    {
        $order = Order::factory()->create();
        $original = $order->trackingCode();

        $this->actingAs($this->admin)
            ->post(route('dashboard.orders.code', $order))
            ->assertRedirect(route('dashboard.orders.show', $order));

        $replacement = session('issued_tracking_code');

        $lookup = app(OrderLookupService::class);

        // How a leaked portal link is revoked.
        $this->assertNull($lookup->find($order->reference, $original));
        $this->assertNotNull($lookup->find($order->reference, $replacement));
    }

    // -----------------------------------------------------------------
    // Guarded columns
    // -----------------------------------------------------------------

    public function test_a_payload_cannot_set_the_reference_status_progress_or_hash(): void
    {
        $this->actingAs($this->admin)->post(route('dashboard.orders.store'), $this->payload([
            'reference' => 'CM-1999-HACKED',
            'status' => Order::STATUS_COMPLETED,
            'progress' => 100,
            'tracking_code_hash' => str_repeat('a', 64),
        ]));

        $order = Order::sole();

        // Three independent layers: the request has no rule, $fillable
        // guards them, and OrderService intersects the payload with an
        // explicit list.
        $this->assertNotSame('CM-1999-HACKED', $order->reference);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(0, $order->progress);
        $this->assertNotSame(str_repeat('a', 64), $order->tracking_code_hash);
    }

    public function test_editing_details_cannot_move_the_order(): void
    {
        $order = Order::factory()->inProgress(40)->create();

        $this->actingAs($this->admin)->put(route('dashboard.orders.update', $order), [
            'client_name' => 'Renamed Client',
            'status' => Order::STATUS_COMPLETED,
            'progress' => 100,
        ]);

        $order->refresh();

        $this->assertSame('Renamed Client', $order->client_name);
        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->status);
        $this->assertSame(40, $order->progress);
    }

    public function test_editing_details_does_not_append_a_timeline_entry(): void
    {
        $order = Order::factory()->create();
        $before = $order->events()->count();

        $this->actingAs($this->admin)->put(route('dashboard.orders.update', $order), [
            'client_name' => 'Corrected Name',
            'note' => ['ar' => 'لا ينبغي أن يظهر'],
        ]);

        // Fixing a typo in a phone number is not news for the client's
        // portal.
        $this->assertSame($before, $order->events()->count());
    }

    // -----------------------------------------------------------------
    // The status graph
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function transitions(): array
    {
        return [
            'pending to in progress' => [Order::STATUS_PENDING, Order::STATUS_IN_PROGRESS, true],
            'pending to cancelled' => [Order::STATUS_PENDING, Order::STATUS_CANCELLED, true],
            'pending straight to completed' => [Order::STATUS_PENDING, Order::STATUS_COMPLETED, false],
            'in progress to in review' => [Order::STATUS_IN_PROGRESS, Order::STATUS_IN_REVIEW, true],
            'in progress back to pending' => [Order::STATUS_IN_PROGRESS, Order::STATUS_PENDING, true],
            'in review to completed' => [Order::STATUS_IN_REVIEW, Order::STATUS_COMPLETED, true],
            'in review back to in progress' => [Order::STATUS_IN_REVIEW, Order::STATUS_IN_PROGRESS, true],
            'completed back to in review' => [Order::STATUS_COMPLETED, Order::STATUS_IN_REVIEW, true],
            'completed to cancelled' => [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED, false],
            'cancelled back to pending' => [Order::STATUS_CANCELLED, Order::STATUS_PENDING, true],
            'cancelled straight to completed' => [Order::STATUS_CANCELLED, Order::STATUS_COMPLETED, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_the_status_graph_is_enforced(string $from, string $to, bool $allowed): void
    {
        $order = Order::factory()->create();
        $order->forceFill(['status' => $from])->save();

        $this->actingAs($this->admin)->patch(route('dashboard.orders.status', $order), ['status' => $to]);

        $this->assertSame($allowed ? $to : $from, $order->fresh()->status);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)->patch(route('dashboard.orders.status', $order), ['status' => 'shipped']);

        // A validated graph, not free text.
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_a_refused_move_says_so_rather_than_erroring(): void
    {
        $order = Order::factory()->completed()->create();

        $this->actingAs($this->admin)
            ->patch(route('dashboard.orders.status', $order), ['status' => Order::STATUS_PENDING])
            ->assertRedirect()
            ->assertSessionHas('error', __('dashboard.orders.transition_refused'));
    }

    public function test_a_move_writes_a_timeline_entry_the_client_can_see(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)->patch(route('dashboard.orders.status', $order), [
            'status' => Order::STATUS_IN_PROGRESS,
            'note' => ['ar' => 'بدأنا التنفيذ'],
            'progress' => 30,
        ]);

        $order->refresh();

        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->status);
        $this->assertSame(30, $order->progress);

        $latest = $order->events->last();
        $this->assertSame(Order::STATUS_IN_PROGRESS, $latest->status);
        $this->assertSame('بدأنا التنفيذ', $latest->note);
    }

    public function test_a_rollback_is_recorded_rather_than_the_history_changing(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)->patch(route('dashboard.orders.status', $order), ['status' => Order::STATUS_IN_PROGRESS]);
        $this->actingAs($this->admin)->patch(route('dashboard.orders.status', $order), ['status' => Order::STATUS_IN_REVIEW]);
        $this->actingAs($this->admin)->patch(route('dashboard.orders.status', $order), ['status' => Order::STATUS_IN_PROGRESS]);

        /*
         * The correction appears as its own line; the earlier entries stay
         * exactly as they were written.
         *
         * Three entries, not four: the factory writes the order directly,
         * so there is no opening entry — only OrderService::create() adds
         * one, which the creation test covers separately.
         */
        $this->assertSame(
            [Order::STATUS_IN_PROGRESS, Order::STATUS_IN_REVIEW, Order::STATUS_IN_PROGRESS],
            $order->fresh()->events->pluck('status')->all(),
        );
    }

    public function test_completing_an_order_fills_the_progress_and_stamps_the_date(): void
    {
        $order = Order::factory()->create();
        $order->forceFill(['status' => Order::STATUS_IN_REVIEW])->save();

        $this->actingAs($this->admin)->patch(route('dashboard.orders.status', $order), ['status' => Order::STATUS_COMPLETED]);

        $order->refresh();

        $this->assertSame(100, $order->progress);
        $this->assertNotNull($order->completed_at);
    }

    public function test_progress_can_be_updated_without_moving_the_order(): void
    {
        $order = Order::factory()->inProgress(20)->create();

        $this->actingAs($this->admin)->patch(route('dashboard.orders.progress', $order), ['progress' => 65]);

        $order->refresh();

        $this->assertSame(65, $order->progress);
        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->status);
    }

    public function test_out_of_range_progress_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('dashboard.orders.progress', $order), ['progress' => 300])
            ->assertSessionHasErrors('progress');
    }

    // -----------------------------------------------------------------
    // The timeline
    // -----------------------------------------------------------------

    public function test_an_admin_can_add_an_update_without_moving_the_order(): void
    {
        $order = Order::factory()->inProgress()->create();

        $this->actingAs($this->admin)->post(route('dashboard.orders.events.store', $order), [
            'note' => ['ar' => 'أرسلنا المسودة الأولى', 'en' => 'First draft sent'],
        ]);

        $event = $order->fresh()->events->last();

        // The status comes from the ORDER, never from the payload — a
        // timeline that contradicts the order it belongs to would be
        // worse than no timeline.
        $this->assertSame(Order::STATUS_IN_PROGRESS, $event->status);
        $this->assertSame('أرسلنا المسودة الأولى', $event->getTranslation('note', 'ar'));
        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->fresh()->status);
    }

    public function test_an_event_cannot_choose_its_own_status(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)->post(route('dashboard.orders.events.store', $order), [
            'note' => ['ar' => 'ملاحظة'],
            'status' => Order::STATUS_COMPLETED,
        ]);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->events->last()->status);
    }

    public function test_a_future_dated_event_is_rejected(): void
    {
        $order = Order::factory()->create();

        // A timeline entry that has not happened yet would show a client
        // an update dated next week.
        $this->actingAs($this->admin)
            ->post(route('dashboard.orders.events.store', $order), [
                'note' => ['ar' => 'ملاحظة'],
                'occurred_at' => now()->addWeek()->toDateString(),
            ])
            ->assertSessionHasErrors('occurred_at');
    }

    public function test_markup_in_an_event_note_is_stripped_on_write(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)->post(route('dashboard.orders.events.store', $order), [
            'note' => ['ar' => 'ملاحظة <script>alert(1)</script>'],
        ]);

        // Sanitised on write AND escaped on render — this note is shown to
        // a client on the public portal.
        $this->assertStringNotContainsString('<script>', $order->fresh()->events->last()->note);
    }

    public function test_an_event_from_another_order_cannot_be_edited_or_deleted(): void
    {
        $order = Order::factory()->create();
        $other = Order::factory()->create();
        $event = $other->events()->create([
            'status' => Order::STATUS_PENDING,
            'note' => ['ar' => 'ملاحظة'],
            'occurred_at' => now(),
        ]);

        // Both ids arrive separately and could name unrelated rows.
        $this->actingAs($this->admin)
            ->delete(route('dashboard.orders.events.destroy', [$order, $event]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->put(route('dashboard.orders.events.update', [$order, $event]), ['note' => ['ar' => 'مُعدّل']])
            ->assertNotFound();

        $this->assertDatabaseHas('order_events', ['id' => $event->id]);
    }

    public function test_an_admin_can_edit_and_delete_an_event_on_the_right_order(): void
    {
        $order = Order::factory()->create();
        $event = $order->events()->create([
            'status' => Order::STATUS_PENDING,
            'note' => ['ar' => 'الأصلية'],
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->admin)->put(route('dashboard.orders.events.update', [$order, $event]), [
            'note' => ['ar' => 'المعدّلة'],
        ]);

        $this->assertSame('المعدّلة', $event->fresh()->note);

        $this->actingAs($this->admin)->delete(route('dashboard.orders.events.destroy', [$order, $event]));

        $this->assertDatabaseMissing('order_events', ['id' => $event->id]);
    }

    public function test_the_timeline_is_escaped_on_the_dashboard_too(): void
    {
        $order = Order::factory()->create();
        OrderEvent::create([
            'order_id' => $order->id,
            'status' => Order::STATUS_PENDING,
            // Written straight to the column, bypassing the sanitiser, so
            // this tests the RENDER rather than the write.
            'note' => ['ar' => '<script>alert(1)</script>'],
            'occurred_at' => now(),
        ]);

        $content = $this->actingAs($this->admin)->get(route('dashboard.orders.show', $order))->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    // -----------------------------------------------------------------
    // Filters
    // -----------------------------------------------------------------

    public function test_the_index_filters_by_status(): void
    {
        Order::factory()->create(['client_name' => 'Pending Client']);
        Order::factory()->completed()->create(['client_name' => 'Delivered Client']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.orders.index', ['status' => Order::STATUS_COMPLETED]))
            ->assertSee('Delivered Client', false)
            ->assertDontSee('Pending Client', false);
    }

    public function test_the_index_filters_by_reference_prefix(): void
    {
        $order = Order::factory()->create(['client_name' => 'Wanted Client']);
        Order::factory()->create(['client_name' => 'Other Client']);

        $this->actingAs($this->admin)
            ->get(route('dashboard.orders.index', ['reference' => substr($order->reference, 0, 10)]))
            ->assertSee('Wanted Client', false)
            ->assertDontSee('Other Client', false);
    }

    public function test_the_index_filters_by_date_range(): void
    {
        Order::factory()->create(['client_name' => 'Today Client', 'placed_at' => now()]);
        Order::factory()->create(['client_name' => 'Old Client', 'placed_at' => now()->subMonths(3)]);

        $this->actingAs($this->admin)
            ->get(route('dashboard.orders.index', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]))
            ->assertSee('Today Client', false)
            ->assertDontSee('Old Client', false);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function junkFilters(): array
    {
        return [
            'an unknown status' => [['status' => 'shipped']],
            'a date that is not a date' => [['date_from' => 'yesterday']],
            'a wildcard in the reference' => [['reference' => '%']],
            'an injection attempt' => [['reference' => "' OR 1=1--"]],
        ];
    }

    /**
     * @param  array<string, string>  $filter
     */
    #[DataProvider('junkFilters')]
    public function test_a_junk_filter_is_dropped_rather_than_emptying_the_list(array $filter): void
    {
        Order::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('dashboard.orders.index', $filter));

        $response->assertOk();
        $this->assertCount(3, $response->viewData('orders'));
    }

    // -----------------------------------------------------------------
    // The graph, directly
    // -----------------------------------------------------------------

    public function test_every_status_has_a_documented_transition_set(): void
    {
        foreach (Order::STATUSES as $status) {
            $this->assertArrayHasKey($status, OrderService::TRANSITIONS, "No transition set for [{$status}].");
        }

        foreach (OrderService::TRANSITIONS as $from => $targets) {
            $this->assertContains($from, Order::STATUSES);

            foreach ($targets as $to) {
                $this->assertContains($to, Order::STATUSES, "[{$from}] can move to unknown status [{$to}].");
            }
        }
    }
}
