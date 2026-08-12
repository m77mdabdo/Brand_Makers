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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // Content
    // -----------------------------------------------------------------

    public function test_service_stores_translations_and_scopes_by_active(): void
    {
        $service = Service::create([
            'slug' => 'visual-identity',
            'is_active' => true,
            'name' => ['en' => 'Visual Identity', 'ar' => 'الهوية البصرية'],
            'summary' => ['en' => 'summary', 'ar' => 'ملخص'],
            'body' => ['en' => 'body', 'ar' => 'محتوى'],
            'requirements' => ['en' => 'reqs', 'ar' => 'متطلبات'],
            'process' => ['en' => 'process', 'ar' => 'خطوات'],
        ]);

        $this->assertDatabaseHas('services', ['slug' => 'visual-identity']);
        $this->assertSame('الهوية البصرية', $service->getTranslation('name', 'ar'));
        $this->assertSame('Visual Identity', $service->getTranslation('name', 'en'));
        $this->assertTrue(Service::active()->whereKey($service->id)->exists());
        $this->assertFalse(Service::active()->whereKey(Service::factory()->inactive()->create()->id)->exists());
    }

    public function test_page_generates_a_slug_and_owns_sections_and_seo_meta(): void
    {
        $page = Page::create([
            'title' => ['en' => 'Home', 'ar' => 'الرئيسية'],
            'body' => ['en' => 'welcome', 'ar' => 'مرحبا'],
            'meta_title' => ['en' => 'meta', 'ar' => 'ميتا'],
            'meta_description' => ['en' => 'meta desc', 'ar' => 'وصف'],
        ]);

        $this->assertSame('home', $page->slug);

        PageSection::create([
            'page_id' => $page->id,
            'key' => 'hero',
            'content' => ['title' => ['ar' => 'بطل', 'en' => 'Hero']],
            'is_active' => true,
        ]);

        $this->assertCount(1, $page->sections);

        $page->seoMeta()->create(['meta_title' => ['en' => 'SEO title']]);

        $this->assertSame('SEO title', $page->refresh()->seoMeta->getTranslation('meta_title', 'en'));
    }

    public function test_page_section_exposes_locale_aware_accessors_over_its_json_blob(): void
    {
        $section = PageSection::factory()->create([
            'content' => [
                'title' => ['ar' => 'عنوان', 'en' => 'Title'],
                'description' => ['ar' => 'وصف', 'en' => 'Description'],
                'icon' => 'compass',
            ],
        ]);

        app()->setLocale('en');
        $this->assertSame('Title', $section->title);

        app()->setLocale('ar');
        $this->assertSame('عنوان', $section->title);
        $this->assertSame('وصف', $section->description);
        $this->assertSame('compass', $section->icon);
    }

    public function test_page_section_falls_back_to_the_fallback_locale(): void
    {
        $section = PageSection::factory()->create([
            'content' => ['title' => ['ar' => 'عربي فقط']],
        ]);

        app()->setLocale('en');

        // config('app.fallback_locale') is 'ar' — an untranslated section
        // shows its Arabic rather than a blank heading.
        $this->assertSame('عربي فقط', $section->title);
        $this->assertNull($section->description);
    }

    public function test_country_faq_testimonial_and_article_are_created_with_slugs_and_scopes(): void
    {
        $country = Country::create(['name' => ['en' => 'Saudi Arabia', 'ar' => 'السعودية']]);
        $this->assertSame('saudi-arabia', $country->slug);

        $faq = Faq::create(['question' => ['en' => 'How?'], 'answer' => ['en' => 'Like this'], 'is_active' => true]);
        $this->assertTrue(Faq::active()->whereKey($faq->id)->exists());

        $testimonial = Testimonial::create(['client_name' => 'Sara', 'quote' => ['en' => 'Great work']]);
        $this->assertSame('Great work', $testimonial->getTranslation('quote', 'en'));

        $article = Article::create(['title' => ['en' => 'News'], 'body' => ['en' => 'body']]);
        $this->assertSame('news', $article->slug);
    }

    public function test_article_live_scope_excludes_drafts_and_scheduled_posts(): void
    {
        Article::factory()->create();
        Article::factory()->draft()->create();
        Article::factory()->scheduled()->create();

        $this->assertSame(1, Article::live()->count());
        $this->assertSame(3, Article::count());
    }

    public function test_comment_belongs_to_an_article_and_scopes_by_status(): void
    {
        $article = Article::factory()->create();

        Comment::factory()->for($article)->create();
        $approved = Comment::factory()->for($article)->approved()->create();

        $this->assertTrue($approved->article->is($article));
        $this->assertSame(1, Comment::approved()->count());
        $this->assertSame(1, Comment::pending()->count());
        $this->assertCount(1, $article->approvedComments);
    }

    public function test_media_belongs_to_its_uploader(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->create(['uploaded_by' => $user->id]);

        $this->assertTrue($media->uploadedBy->is($user));
    }

    // -----------------------------------------------------------------
    // Settings and navigation
    // -----------------------------------------------------------------

    public function test_setting_casts_its_value_to_an_array(): void
    {
        $setting = Setting::create([
            'key' => 'contact_phone',
            'value' => ['phone' => '+966500000000'],
            'group' => 'contact',
        ]);

        $this->assertIsArray($setting->fresh()->value);
        $this->assertSame('+966500000000', $setting->fresh()->value['phone']);
    }

    public function test_tracking_setting_validates_a_value_against_its_published_format(): void
    {
        $valid = TrackingSetting::create(['key' => 'meta_pixel_id', 'value' => '123456789012345', 'is_active' => true]);
        $invalid = TrackingSetting::create(['key' => 'meta_pixel_id_x', 'value' => '"><script>', 'is_active' => true]);
        $wrongShape = TrackingSetting::create(['key' => 'ga4_measurement_id', 'value' => 'not-an-id']);

        $this->assertTrue($valid->matchesItsFormat());
        $this->assertFalse($invalid->matchesItsFormat(), 'An unknown key has no format and must never validate.');
        $this->assertFalse($wrongShape->matchesItsFormat());
        $this->assertTrue(TrackingSetting::active()->whereKey($valid->id)->exists());
    }

    public function test_menu_item_nests_children_and_hides_invisible_ones(): void
    {
        $parent = MenuItem::factory()->dropdownParent()->create();
        MenuItem::factory()->for($parent, 'parent')->create(['sort_order' => 1]);
        MenuItem::factory()->for($parent, 'parent')->hidden()->create(['sort_order' => 2]);

        $this->assertCount(2, $parent->children);
        $this->assertCount(1, $parent->visibleChildren);
        $this->assertTrue($parent->hasVisibleChildren());
        $this->assertNull($parent->href(), 'A link_type of "none" has no destination.');
    }

    public function test_menu_item_refuses_to_resolve_a_route_outside_the_whitelist(): void
    {
        $rogue = MenuItem::factory()->route('dashboard.leads.destroy')->create();

        $this->assertNull($rogue->href(), 'A non-whitelisted route must never reach the navbar.');
    }

    public function test_menu_item_url_links_carry_noopener_when_they_open_a_new_tab(): void
    {
        $external = MenuItem::factory()->url('https://example.com', '_blank')->create();
        $internal = MenuItem::factory()->url('/about')->create();

        $this->assertSame('https://example.com', $external->href());
        $this->assertSame('noopener', $external->linkRel());
        $this->assertNull($internal->linkRel());
    }

    public function test_deleting_a_menu_parent_reparents_its_children_instead_of_deleting_them(): void
    {
        $parent = MenuItem::factory()->dropdownParent()->create();
        $child = MenuItem::factory()->for($parent, 'parent')->create();

        $parent->delete();

        $this->assertDatabaseHas('menu_items', ['id' => $child->id]);
        $this->assertNull($child->fresh()->parent_id);
    }

    // -----------------------------------------------------------------
    // CRM
    // -----------------------------------------------------------------

    public function test_lead_belongs_to_a_requested_service_and_casts_touch_data(): void
    {
        $service = Service::factory()->create();

        $lead = Lead::create([
            'full_name' => 'Ahmed Al-Otaibi',
            'phone' => '+966501234567',
            'requested_service_id' => $service->id,
            'type' => Lead::TYPE_CONSULTATION,
            'first_touch' => ['utm_source' => 'google'],
        ]);

        $this->assertTrue($lead->requestedService->is($service));
        $this->assertIsArray($lead->first_touch);
        $this->assertSame('google', $lead->first_touch['utm_source']);
    }

    public function test_lead_conversion_scopes_only_count_won_event_types(): void
    {
        $won = Lead::factory()->create();
        ConversionEvent::factory()->for($won)->create();

        $milestoneOnly = Lead::factory()->create();
        ConversionEvent::factory()->for($milestoneOnly)->milestone()->create();

        $this->assertTrue($won->isConverted());
        $this->assertFalse($milestoneOnly->isConverted());
        $this->assertSame(1, Lead::converted()->count());
        $this->assertSame(1, Lead::notConverted()->count());
    }

    public function test_campaign_reaches_its_leads_and_their_conversions(): void
    {
        $campaign = Campaign::factory()->create();
        $lead = Lead::factory()->create(['linked_campaign_id' => $campaign->id]);
        ConversionEvent::factory()->for($lead)->create(['value' => 5000]);

        $this->assertCount(1, $campaign->leads);
        $this->assertCount(1, $campaign->conversionEvents);
        $this->assertSame('5000.00', (string) $campaign->conversionEvents->first()->value);
    }

    public function test_lead_source_counts_leads_by_key_rather_than_by_foreign_key(): void
    {
        $source = LeadSource::factory()->create(['key' => 'behance']);
        Lead::factory()->count(2)->create(['source_platform' => 'behance']);
        Lead::factory()->create(['source_platform' => 'a-source-nobody-configured']);

        $this->assertSame(2, $source->leadsCount());
        $this->assertSame(3, Lead::count(), 'A lead with an unknown source is still recorded.');
    }

    public function test_visitor_session_reports_its_journey_source_and_duration(): void
    {
        $session = VisitorSession::factory()->create(['total_duration_seconds' => 187]);

        $this->assertSame('google', $session->sourcePlatform());
        $this->assertSame('brand-search', $session->campaignName());
        $this->assertSame('3:07', $session->durationForHumans());
        $this->assertFalse($session->hasConverted());
        $this->assertSame(1, VisitorSession::notConverted()->count());
    }

    public function test_visitor_session_drops_malformed_journey_entries_rather_than_failing(): void
    {
        $session = VisitorSession::factory()->create([
            'events' => [
                ['type' => 'pageview', 'ts' => now()->toIso8601String()],
                ['url' => '/no-type-or-ts'],
                'not even an array',
            ],
        ]);

        $this->assertCount(1, $session->journey());
    }

    public function test_visitor_session_reports_a_truncated_journey_as_truncated(): void
    {
        $this->assertTrue(VisitorSession::factory()->truncated()->create()->isTruncated());
        $this->assertFalse(VisitorSession::factory()->create()->isTruncated());
    }

    // -----------------------------------------------------------------
    // Packages, estimator, bookings, orders
    // -----------------------------------------------------------------

    public function test_package_distinguishes_no_price_from_a_price_of_zero(): void
    {
        $onRequest = Package::factory()->priceOnRequest()->create();
        $free = Package::factory()->create(['price' => 0]);

        $this->assertTrue($onRequest->isPriceOnRequest());
        $this->assertFalse($free->isPriceOnRequest());
    }

    public function test_package_feature_list_is_localised_and_drops_malformed_entries(): void
    {
        $package = Package::factory()->create([
            'features' => [
                ['ar' => 'شعار', 'en' => 'Logo'],
                ['ar' => 'ألوان'],
                ['en' => ''],
                12345,
            ],
        ]);

        // 'ألوان' survives in English via the 'ar' fallback; the empty
        // string and the integer are dropped.
        $this->assertSame(['Logo', 'ألوان'], $package->featureList('en'));
        $this->assertSame(['شعار', 'ألوان'], $package->featureList('ar'));
    }

    public function test_estimator_totals_are_recomputed_from_the_database_not_from_input(): void
    {
        EstimatorItem::factory()->create(['key' => 'logo', 'price' => 6000]);
        EstimatorItem::factory()->create(['key' => 'guide', 'price' => 5000]);
        EstimatorItem::factory()->inactive()->create(['key' => 'retired', 'price' => 99999]);

        $this->assertSame('11000.00', EstimatorItem::estimateFor(['logo', 'guide']));
        $this->assertSame(
            '6000.00',
            EstimatorItem::estimateFor(['logo', 'retired', 'a-key-that-does-not-exist']),
            'Inactive and unknown keys contribute nothing rather than erroring.'
        );
        $this->assertSame('0.00', EstimatorItem::estimateFor([]));
    }

    public function test_estimator_items_group_for_rendering(): void
    {
        EstimatorItem::factory()->count(2)->inGroup('identity')->create();
        EstimatorItem::factory()->inGroup('print')->create();
        EstimatorItem::factory()->inGroup('print')->inactive()->create();

        $grouped = EstimatorItem::grouped();

        $this->assertSame(['identity', 'print'], $grouped->keys()->sort()->values()->all());
        $this->assertCount(2, $grouped['identity']);
        $this->assertCount(1, $grouped['print']);
    }

    public function test_booking_availability_generates_only_slots_that_fit_before_closing(): void
    {
        $day = BookingAvailability::factory()->make(['opens_at' => '09:00:00', 'closes_at' => '11:00:00', 'slot_minutes' => 45]);

        // 10:30 would run to 11:15 and overrun the close, so it is not
        // offered.
        $this->assertSame(['09:00', '09:45'], $day->slots());
    }

    public function test_booking_availability_returns_no_slots_for_a_closed_or_nonsensical_day(): void
    {
        $this->assertSame([], BookingAvailability::factory()->closed()->make()->slots());
        $this->assertSame([], BookingAvailability::factory()->make(['opens_at' => '17:00:00', 'closes_at' => '09:00:00'])->slots());
        $this->assertSame([], BookingAvailability::factory()->make(['slot_minutes' => 0])->slots());
    }

    public function test_a_cancelled_booking_releases_its_slot(): void
    {
        $slot = now()->addWeek()->startOfHour();

        Booking::factory()->at($slot)->confirmed()->create();
        Booking::factory()->at($slot)->cancelled()->create();

        $this->assertSame(2, Booking::where('scheduled_at', $slot)->count());
        $this->assertSame(1, Booking::holdingSlot($slot)->count());
    }

    public function test_booking_blackout_dates_block_a_day(): void
    {
        $blackout = BookingBlackoutDate::factory()->create();

        $this->assertTrue(BookingBlackoutDate::blocks($blackout->date));
        $this->assertFalse(BookingBlackoutDate::blocks($blackout->date->copy()->addDay()));
    }

    public function test_order_transition_records_a_timeline_entry_for_every_status_change(): void
    {
        $order = Order::factory()->create();

        $order->transitionTo(Order::STATUS_IN_PROGRESS, ['ar' => 'بدأ العمل'], 40);
        $order->transitionTo(Order::STATUS_COMPLETED, ['ar' => 'تم التسليم']);

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(100, $order->fresh()->progress, 'Completing an order forces progress to 100.');
        $this->assertNotNull($order->fresh()->completed_at);
        $this->assertCount(2, $order->events);
        $this->assertSame('بدأ العمل', $order->events->first()->getTranslation('note', 'ar'));
        $this->assertTrue($order->fresh()->isComplete());
    }

    public function test_order_rejects_an_unknown_status_and_clamps_progress(): void
    {
        $order = Order::factory()->create();

        $this->assertFalse($order->transitionTo('shipped-to-mars'));
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertCount(0, $order->events);

        $order->setProgress(500);
        $this->assertSame(100, $order->progress);

        $order->setProgress(-20);
        $this->assertSame(0, $order->progress);
    }

    public function test_order_event_belongs_to_its_order(): void
    {
        $order = Order::factory()->create();
        $order->transitionTo(Order::STATUS_IN_PROGRESS);

        $this->assertTrue($order->events->first()->order->is($order));
    }

    public function test_seo_meta_resolves_back_to_its_owner_through_the_morph_map(): void
    {
        $service = Service::factory()->create();
        $meta = SeoMeta::factory()->forOwner($service)->create();

        $this->assertSame('service', $meta->fresh()->seo_metable_type);
        $this->assertTrue($meta->fresh()->seoMetable->is($service));
    }
}
