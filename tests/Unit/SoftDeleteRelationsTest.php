<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\ConversionEvent;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Package;
use App\Models\Service;
use App\Models\VisitorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUG-2, baked in from day one.
 *
 * The rule this suite pins down: a belongsTo pointing at a soft-deleting
 * parent resolves withTrashed(), because the child is a historical record
 * whose foreign key survives the parent being archived. Without it the
 * relation silently resolves to null and the dashboard shows a blank where
 * a name should be — on exactly the rows an operator most needs to read.
 *
 * The hasMany direction deliberately does NOT do this: an archived lead
 * should not count towards a service's enquiry volume.
 */
class SoftDeleteRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lead_still_names_the_service_it_asked_about_after_that_service_is_archived(): void
    {
        $service = Service::factory()->create();
        $lead = Lead::factory()->create(['requested_service_id' => $service->id]);

        $service->delete();

        $this->assertSoftDeleted($service);
        $this->assertNotNull($lead->fresh()->requestedService);
        $this->assertTrue($lead->fresh()->requestedService->is($service));
    }

    public function test_a_conversion_event_still_names_its_lead_after_the_lead_is_archived(): void
    {
        $lead = Lead::factory()->create();
        $event = ConversionEvent::factory()->for($lead)->create();

        $lead->delete();

        $this->assertNotNull($event->fresh()->lead, 'A revenue row with no lead cannot be reconciled.');
        $this->assertTrue($event->fresh()->lead->is($lead));
    }

    public function test_a_visitor_session_stays_marked_converted_after_its_lead_is_archived(): void
    {
        $lead = Lead::factory()->create();
        $session = VisitorSession::factory()->converted($lead)->create();

        $lead->delete();

        $this->assertNotNull($session->fresh()->lead);
        $this->assertTrue($session->fresh()->hasConverted());
    }

    public function test_a_comment_still_names_its_article_after_the_article_is_archived(): void
    {
        $article = Article::factory()->create();
        $comment = Comment::factory()->for($article)->create();

        $article->delete();

        $this->assertNotNull($comment->fresh()->article, 'The moderation queue cannot act on a blank row.');
    }

    public function test_a_booking_still_names_its_service_and_lead_after_both_are_archived(): void
    {
        $service = Service::factory()->create();
        $lead = Lead::factory()->create();
        $booking = Booking::factory()->create(['service_id' => $service->id, 'lead_id' => $lead->id]);

        $service->delete();
        $lead->delete();

        $this->assertNotNull($booking->fresh()->service);
        $this->assertNotNull($booking->fresh()->lead);
    }

    public function test_an_order_still_names_the_package_that_was_bought_after_it_is_retired(): void
    {
        $package = Package::factory()->create();
        $order = Order::factory()->create(['package_id' => $package->id]);

        $package->delete();

        $this->assertNotNull($order->fresh()->package, 'An order must keep showing what was actually bought.');
        $this->assertTrue($order->fresh()->package->is($package));
    }

    public function test_an_order_event_still_names_its_order_after_the_order_is_archived(): void
    {
        $order = Order::factory()->create();
        $order->transitionTo(Order::STATUS_IN_PROGRESS);
        $event = $order->events->first();

        $order->delete();

        $this->assertNotNull($event->fresh()->order);
    }

    /**
     * The other direction, on purpose. hasMany stays scoped: an archived
     * enquiry is not a live enquiry, and counting it would inflate the
     * service's numbers.
     */
    public function test_a_service_does_not_count_archived_leads(): void
    {
        $service = Service::factory()->create();
        Lead::factory()->count(2)->create(['requested_service_id' => $service->id]);
        Lead::factory()->create(['requested_service_id' => $service->id])->delete();

        $this->assertCount(2, $service->fresh()->leads);
    }

    public function test_archiving_a_soft_deleting_parent_does_not_delete_its_children(): void
    {
        $lead = Lead::factory()->create();
        $order = Order::factory()->create(['lead_id' => $lead->id]);

        $lead->delete();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'lead_id' => $lead->id]);
    }
}
