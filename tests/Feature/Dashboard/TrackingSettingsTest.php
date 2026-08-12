<?php

namespace Tests\Feature\Dashboard;

use App\Models\TrackingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrackingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_editor_is_admin_only(): void
    {
        $this->get(route('dashboard.tracking.edit'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('dashboard.tracking.edit'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('dashboard.tracking.edit'))->assertOk();
    }

    public function test_it_renders_every_supported_platform(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.tracking.edit'));

        foreach (array_keys(TrackingSetting::FORMATS) as $key) {
            $response->assertSee(__('dashboard.tracking.fields.'.$key), false);
        }
    }

    public function test_a_valid_id_is_stored_and_can_be_enabled(): void
    {
        $this->actingAs($this->admin)->put(route('dashboard.tracking.update'), [
            'meta_pixel_id' => '123456789012345',
            'ga4_measurement_id' => 'G-ABC1234567',
            'active' => ['meta_pixel_id' => '1'],
        ])->assertRedirect(route('dashboard.tracking.edit'));

        $meta = TrackingSetting::where('key', 'meta_pixel_id')->firstOrFail();

        $this->assertSame('123456789012345', $meta->value);
        $this->assertTrue($meta->is_active);
        $this->assertTrue($meta->matchesItsFormat());

        // Stored but not enabled — a value alone must not switch it on.
        $ga4 = TrackingSetting::where('key', 'ga4_measurement_id')->firstOrFail();
        $this->assertSame('G-ABC1234567', $ga4->value);
        $this->assertFalse($ga4->is_active);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function malformedIds(): array
    {
        return [
            'script break-out in the pixel id' => ['meta_pixel_id', '1234"></script><script>alert(1)</script>'],
            'letters in a numeric id' => ['meta_pixel_id', 'not-a-number'],
            'wrong prefix on GTM' => ['gtm_container_id', 'ABC-1234567'],
            'wrong prefix on GA4' => ['ga4_measurement_id', 'UA-12345-1'],
            'wrong prefix on Google Ads' => ['google_ads_conversion_id', 'AB-123456'],
            'markup in the TikTok id' => ['tiktok_pixel_id', '<img src=x onerror=alert(1)>'],
        ];
    }

    #[DataProvider('malformedIds')]
    public function test_a_malformed_id_is_rejected(string $key, string $value): void
    {
        $this->actingAs($this->admin)
            ->put(route('dashboard.tracking.update'), [$key => $value])
            ->assertSessionHasErrors($key);

        // Nothing that is not an ID reaches the table — these values are
        // interpolated into inline <script> blocks on every public page.
        $this->assertNotSame($value, TrackingSetting::where('key', $key)->value('value'));
    }

    public function test_an_unknown_key_is_never_written(): void
    {
        $before = TrackingSetting::count();

        $this->actingAs($this->admin)->put(route('dashboard.tracking.update'), [
            'meta_pixel_id' => '123456789012345',
            'evil_pixel_id' => '<script>alert(1)</script>',
            'app_key' => 'base64:pwned',
        ]);

        // The service iterates FORMATS, never the payload.
        $this->assertDatabaseMissing('tracking_settings', ['key' => 'evil_pixel_id']);
        $this->assertDatabaseMissing('tracking_settings', ['key' => 'app_key']);
        $this->assertSame($before, TrackingSetting::count());
    }

    public function test_clearing_an_id_also_switches_it_off(): void
    {
        $this->actingAs($this->admin)->put(route('dashboard.tracking.update'), [
            'meta_pixel_id' => '123456789012345',
            'active' => ['meta_pixel_id' => '1'],
        ]);

        $this->assertTrue(TrackingSetting::where('key', 'meta_pixel_id')->value('is_active'));

        $this->actingAs($this->admin)->put(route('dashboard.tracking.update'), [
            'meta_pixel_id' => '',
            'active' => ['meta_pixel_id' => '1'],
        ]);

        $setting = TrackingSetting::where('key', 'meta_pixel_id')->firstOrFail();

        // A pixel with no ID cannot fire, so it is deactivated rather than
        // left switched on pointing at nothing.
        $this->assertNull($setting->value);
        $this->assertFalse($setting->is_active);
    }

    public function test_a_fresh_install_has_every_key_present_and_inert(): void
    {
        $this->assertSame(count(TrackingSetting::FORMATS), TrackingSetting::count());
        $this->assertSame(0, TrackingSetting::where('is_active', true)->count());
        $this->assertSame(0, TrackingSetting::whereNotNull('value')->count());
    }
}
