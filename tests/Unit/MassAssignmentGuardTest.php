<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every column that grants a privilege, hides work, or authenticates
 * someone must be unreachable from a mass-assignment payload.
 *
 * These are not style assertions. Each one describes a concrete
 * escalation: registering yourself as an admin, marking your own enquiry
 * as already dealt with, confirming your own booking, completing your own
 * order, or — worst — choosing your own tracking code and then using it to
 * read the order back.
 */
class MassAssignmentGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function guardedColumns(): array
    {
        return [
            'users.is_admin' => [User::class, 'is_admin'],
            'leads.is_handled' => [Lead::class, 'is_handled'],
            'lead_sources.is_system' => [LeadSource::class, 'is_system'],
            'menu_items.is_system' => [MenuItem::class, 'is_system'],
            'bookings.status' => [Booking::class, 'status'],
            'orders.status' => [Order::class, 'status'],
            'orders.progress' => [Order::class, 'progress'],
            'orders.reference' => [Order::class, 'reference'],
            'orders.tracking_code_hash' => [Order::class, 'tracking_code_hash'],
        ];
    }

    #[DataProvider('guardedColumns')]
    public function test_the_column_is_not_mass_assignable(string $model, string $column): void
    {
        $this->assertNotContains($column, (new $model)->getFillable());
    }

    public function test_is_admin_cannot_be_set_through_create(): void
    {
        $user = User::create([
            'name' => 'Attacker',
            'email' => 'attacker@example.test',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->is_admin);

        // …but explicit assignment still works, which is how the seeder
        // creates the real administrator.
        $user->is_admin = true;
        $user->save();

        $this->assertTrue($user->fresh()->is_admin);
        $this->assertIsBool($user->fresh()->is_admin);
    }

    public function test_a_public_submission_cannot_mark_its_own_lead_as_handled(): void
    {
        $lead = Lead::create([
            'full_name' => 'Someone',
            'phone' => '+966500000000',
            'is_handled' => true,
        ]);

        $this->assertFalse($lead->fresh()->is_handled);
        $this->assertSame(1, Lead::unhandled()->count(), 'The enquiry must still appear in the inbox.');

        $lead->markHandled();
        $this->assertTrue($lead->fresh()->is_handled);
    }

    public function test_a_booking_cannot_confirm_or_cancel_itself_from_a_payload(): void
    {
        $booking = Booking::create([
            'name' => 'Someone',
            'email' => 'someone@example.test',
            'scheduled_at' => now()->addWeek(),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);

        $booking->markConfirmed();
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_an_order_cannot_set_its_own_status_progress_reference_or_tracking_code(): void
    {
        $chosenHash = hash('sha256', 'the-code-i-picked');

        $order = Order::create([
            'client_name' => 'Someone',
            'status' => Order::STATUS_COMPLETED,
            'progress' => 100,
            'reference' => 'CM-0000-CHOSEN',
            'tracking_code_hash' => $chosenHash,
        ]);

        $fresh = $order->fresh();

        $this->assertSame(Order::STATUS_PENDING, $fresh->status);
        $this->assertSame(0, $fresh->progress);
        $this->assertNotSame('CM-0000-CHOSEN', $fresh->reference);
        $this->assertNotSame(
            $chosenHash,
            $fresh->tracking_code_hash,
            'An attacker who could choose the hash could choose the code, and then read the order.'
        );
        $this->assertNull(
            Order::findByTrackingCode('the-code-i-picked'),
            'The code the attacker tried to install must not open anything.'
        );
    }

    public function test_a_menu_item_cannot_claim_system_status_to_dodge_deletion(): void
    {
        $item = MenuItem::create([
            'label' => ['ar' => 'عنصر'],
            'link_type' => 'route',
            'route_name' => 'home',
            'is_system' => true,
        ]);

        $this->assertFalse($item->fresh()->is_system);
    }

    public function test_a_lead_source_cannot_claim_system_status(): void
    {
        $source = LeadSource::create([
            'key' => 'made-up',
            'label' => 'Made Up',
            'is_system' => true,
        ]);

        $this->assertFalse($source->fresh()->is_system);
    }

    public function test_the_tracking_code_hash_never_leaves_the_model_through_serialisation(): void
    {
        $order = Order::factory()->create();

        $this->assertArrayNotHasKey('tracking_code_hash', $order->toArray());
        $this->assertStringNotContainsString('tracking_code_hash', $order->toJson());
    }
}
