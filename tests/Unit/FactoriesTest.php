<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Booking;
use App\Models\BookingAvailability;
use App\Models\BookingBlackoutDate;
use App\Models\Campaign;
use App\Models\Comment;
use App\Models\ConversionEvent;
use App\Models\Country;
use App\Models\EstimatorItem;
use App\Models\Faq;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Package;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\TrackingSetting;
use App\Models\User;
use App\Models\VisitorSession;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every model has a working factory. These assertions are deliberately
 * thin — the point is that each factory persists against the REAL schema,
 * including NOT NULL columns with no portable default and every unique
 * index.
 */
class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string}>
     */
    public static function models(): array
    {
        return [
            'Article' => [Article::class],
            'Booking' => [Booking::class],
            'BookingAvailability' => [BookingAvailability::class],
            'BookingBlackoutDate' => [BookingBlackoutDate::class],
            'Campaign' => [Campaign::class],
            'Comment' => [Comment::class],
            'ConversionEvent' => [ConversionEvent::class],
            'Country' => [Country::class],
            'EstimatorItem' => [EstimatorItem::class],
            'Faq' => [Faq::class],
            'Lead' => [Lead::class],
            'LeadSource' => [LeadSource::class],
            'Media' => [Media::class],
            'MenuItem' => [MenuItem::class],
            'Order' => [Order::class],
            'OrderEvent' => [OrderEvent::class],
            'Package' => [Package::class],
            'Page' => [Page::class],
            'PageSection' => [PageSection::class],
            'SeoMeta' => [SeoMeta::class],
            'Service' => [Service::class],
            'Setting' => [Setting::class],
            'Testimonial' => [Testimonial::class],
            'TrackingSetting' => [TrackingSetting::class],
            'User' => [User::class],
            'VisitorSession' => [VisitorSession::class],
        ];
    }

    #[DataProvider('models')]
    public function test_the_factory_persists_against_the_real_schema(string $model): void
    {
        $instance = $model::factory()->create();

        $this->assertTrue($instance->exists);
        $this->assertDatabaseHas($instance->getTable(), [$instance->getKeyName() => $instance->getKey()]);
    }

    #[DataProvider('models')]
    public function test_the_factory_makes_several_without_unique_collisions(string $model): void
    {
        $this->assertCount(3, $model::factory()->count(3)->create());
    }

    #[DataProvider('models')]
    public function test_every_model_uses_has_factory(string $model): void
    {
        $this->assertContains(
            HasFactory::class,
            class_uses_recursive($model)
        );
    }

    // ---------------------------------------------------------------
    // States that encode real behaviour
    // ---------------------------------------------------------------

    public function test_article_states(): void
    {
        $this->assertTrue(Article::factory()->create()->is_published);
        $this->assertFalse(Article::factory()->draft()->create()->is_published);
        $this->assertTrue(Article::factory()->scheduled()->create()->published_at->isFuture());
    }

    public function test_conversion_event_always_sets_occurred_at(): void
    {
        // NOT NULL with no portable default — SQLite rejects an omission
        // where MySQL would silently supply "now".
        $this->assertNotNull(ConversionEvent::factory()->create()->occurred_at);
        $this->assertSame(0, ConversionEvent::whereNull('occurred_at')->count());
    }

    public function test_conversion_event_milestone_state_is_not_a_won_type(): void
    {
        $event = ConversionEvent::factory()->milestone()->create();

        $this->assertNotContains($event->event_type, ConversionEvent::WON_TYPES);
        $this->assertFalse($event->lead->isConverted());
    }

    public function test_comment_defaults_to_pending(): void
    {
        $this->assertSame(Comment::STATUS_PENDING, Comment::factory()->create()->status);
        $this->assertSame(Comment::STATUS_APPROVED, Comment::factory()->approved()->create()->status);
    }

    public function test_relationship_factories_wire_up_correctly(): void
    {
        $this->assertNotNull(PageSection::factory()->create()->page);
        $this->assertNotNull(Comment::factory()->create()->article);
        $this->assertNotNull(ConversionEvent::factory()->create()->lead);
        $this->assertNotNull(SeoMeta::factory()->create()->seoMetable);
        $this->assertNotNull(Booking::factory()->create()->service);
        $this->assertNotNull(Order::factory()->create()->package);
        $this->assertNotNull(OrderEvent::factory()->create()->order);
    }

    public function test_translatable_factories_populate_both_locales(): void
    {
        foreach ([Service::factory(), Package::factory(), Country::factory(), EstimatorItem::factory()] as $factory) {
            $model = $factory->create();
            $field = $model->translatable[0];

            $this->assertNotSame('', $model->getTranslation($field, 'ar', false));
            $this->assertNotSame('', $model->getTranslation($field, 'en', false));
        }
    }

    public function test_inactive_states_are_excluded_by_the_active_scopes(): void
    {
        Service::factory()->create();
        Service::factory()->inactive()->create();
        Country::factory()->inactive()->create();
        Faq::factory()->inactive()->create();
        Testimonial::factory()->inactive()->create();
        Package::factory()->inactive()->create();
        EstimatorItem::factory()->inactive()->create();

        $this->assertSame(1, Service::active()->count());
        $this->assertSame(0, Country::active()->count());
        $this->assertSame(0, Faq::active()->count());
        $this->assertSame(0, Testimonial::active()->count());
        $this->assertSame(0, Package::active()->count());
        $this->assertSame(0, EstimatorItem::active()->count());
    }

    public function test_guarded_column_states_actually_apply_them(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->is_admin);
        $this->assertTrue(LeadSource::factory()->system()->create()->is_system);
        $this->assertTrue(MenuItem::factory()->system('nav-test')->create()->is_system);
        $this->assertTrue(Lead::factory()->handled()->create()->is_handled);
        $this->assertSame(Booking::STATUS_CONFIRMED, Booking::factory()->confirmed()->create()->status);
        $this->assertSame(Order::STATUS_COMPLETED, Order::factory()->completed()->create()->status);
        $this->assertSame(100, Order::factory()->completed()->create()->progress);
    }

    public function test_lead_campaign_state_matches_a_campaign_external_id(): void
    {
        $campaign = Campaign::factory()->create();
        $lead = Lead::factory()->fromCampaign($campaign->external_campaign_id)->create();

        $this->assertSame($campaign->external_campaign_id, $lead->campaign_id);
    }

    public function test_booking_availability_factory_can_build_a_full_week(): void
    {
        // day_of_week carries a unique index; the factory must cycle, not
        // repeat.
        $week = BookingAvailability::factory()->count(7)->create();

        $this->assertCount(7, $week->pluck('day_of_week')->unique());
    }
}
