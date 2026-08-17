<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\ConversionEvent;
use App\Models\Lead;
use App\Models\Service;
use App\Models\VisitorSession;
use Illuminate\Database\Seeder;

/**
 * FABRICATED enquiries, the visitor journeys that "produced" them, and the
 * revenue attached to them. Loaded only via DemoDataSeeder.
 *
 * This is the one demo seeder whose absence from production matters most:
 * every figure on the Reports screen — conversion rate, revenue, ROI
 * against campaign spend — is computed from these rows. Seeding them in
 * production would make the dashboard confidently report a business that
 * does not exist.
 *
 * Idempotent on the sample email addresses, which are all @example.test —
 * a reserved TLD that can never belong to a real person.
 */
class DemoLeadSeeder extends Seeder
{
    public function run(): void
    {
        $services = Service::orderBy('sort_order')->get();

        if ($services->isEmpty()) {
            $this->command?->warn('No services found — run the production seed first.');

            return;
        }

        foreach ($this->leads() as $index => $definition) {
            $lead = Lead::updateOrCreate(
                ['email' => $definition['email']],
                [
                    'full_name' => $definition['full_name'].' '.DemoDataSeeder::SAMPLE_MARKER,
                    'phone' => $definition['phone'],
                    'type' => $definition['type'],
                    'message' => $definition['message'],
                    'requested_service_id' => $services[$index % $services->count()]->id,
                    'source_platform' => $definition['source_platform'],
                    'campaign_id' => $definition['campaign_id'] ?? null,
                    'utm_source' => $definition['source_platform'],
                    'utm_campaign' => $definition['campaign_id'] ?? null,
                    'landing_page_url' => $definition['landing_page_url'],
                    'linked_campaign_id' => $this->resolveCampaign($definition['campaign_id'] ?? null),
                    'first_touch' => ['utm_source' => $definition['source_platform']],
                    'latest_touch' => ['utm_source' => $definition['source_platform']],
                    'consent_given' => true,
                    'consented_at' => now()->subDays(30 - $index),
                ]
            );

            // is_handled is guarded, so it goes through the model's own
            // transition — the same route the inbox uses.
            if ($definition['handled'] ?? false) {
                $lead->markHandled();
            }

            $this->seedJourneyFor($lead, $index);
            $this->seedConversionsFor($lead, $definition['conversions'] ?? []);
        }
    }

    private function resolveCampaign(?string $externalId): ?int
    {
        if ($externalId === null) {
            return null;
        }

        return Campaign::where('external_campaign_id', $externalId)->value('id');
    }

    private function seedJourneyFor(Lead $lead, int $index): void
    {
        $startedAt = now()->subDays(30 - $index)->subMinutes(20);

        VisitorSession::updateOrCreate(
            ['session_uid' => 'sample-session-'.$index],
            [
                'started_at' => $startedAt,
                'last_activity_at' => (clone $startedAt)->addMinutes(11),
                'entry_url' => '/services',
                'referrer' => 'https://www.google.com/',
                'attribution' => [
                    'first_touch' => ['utm_source' => $lead->source_platform],
                    'latest_touch' => ['utm_source' => $lead->source_platform],
                ],
                'device_type' => $index % 2 === 0 ? 'mobile' : 'desktop',
                'browser' => $index % 2 === 0 ? 'Safari' : 'Chrome',
                'platform' => $index % 2 === 0 ? 'iOS' : 'macOS',
                // Passed as a full address on purpose: the model's mutator
                // truncates it, so even the demo data cannot introduce a
                // raw IP into the table.
                'ip_prefix' => '203.0.113.'.(10 + $index),
                'country' => 'SA',
                'events' => [
                    ['type' => VisitorSession::TYPE_PAGEVIEW, 'url' => '/services', 'label' => null, 'ts' => $startedAt->toIso8601String(), 'duration_ms' => 42000],
                    ['type' => VisitorSession::TYPE_PAGEVIEW, 'url' => '/packages', 'label' => null, 'ts' => (clone $startedAt)->addMinutes(3)->toIso8601String(), 'duration_ms' => 96000],
                    ['type' => VisitorSession::TYPE_ACTION, 'url' => '/booking', 'label' => 'form_submit', 'ts' => (clone $startedAt)->addMinutes(9)->toIso8601String(), 'duration_ms' => null],
                ],
                'page_count' => 3,
                'total_duration_seconds' => 660,
                'lead_id' => $lead->id,
            ]
        );
    }

    /**
     * @param  array<int, array{type: string, value: float|null, days_ago: int}>  $conversions
     */
    private function seedConversionsFor(Lead $lead, array $conversions): void
    {
        foreach ($conversions as $conversion) {
            ConversionEvent::updateOrCreate(
                [
                    'lead_id' => $lead->id,
                    'event_type' => $conversion['type'],
                ],
                [
                    'value' => $conversion['value'],
                    'currency' => 'SAR',
                    'occurred_at' => now()->subDays($conversion['days_ago']),
                    'notes' => DemoDataSeeder::SAMPLE_MARKER,
                ]
            );
        }
    }

    /**
     * All emails use the reserved .test TLD, so no sample row can ever
     * collide with a real person's address.
     *
     * @return array<int, array<string, mixed>>
     */
    private function leads(): array
    {
        return [
            [
                'full_name' => 'نورة العتيبي',
                'email' => 'noura.sample@example.test',
                'phone' => '+966500000001',
                'type' => Lead::TYPE_CONSULTATION,
                'source_platform' => 'google',
                'campaign_id' => 'sample_google_search',
                'landing_page_url' => '/services/llc-formation',
                'message' => 'بيانات عينة — طلب استشارة لتأسيس شركة ذات مسؤولية محدودة. '.DemoDataSeeder::SAMPLE_MARKER,
                'handled' => true,
                'conversions' => [
                    ['type' => 'qualified', 'value' => null, 'days_ago' => 25],
                    ['type' => 'contract_signed', 'value' => 38000.00, 'days_ago' => 18],
                ],
            ],
            [
                'full_name' => 'خالد المطيري',
                'email' => 'khalid.sample@example.test',
                'phone' => '+966500000002',
                'type' => Lead::TYPE_BOOKING,
                'source_platform' => 'instagram',
                'campaign_id' => 'sample_meta_retargeting',
                'landing_page_url' => '/booking',
                'message' => 'بيانات عينة — حجز جلسة استشارة. '.DemoDataSeeder::SAMPLE_MARKER,
                'conversions' => [
                    ['type' => 'meeting_booked', 'value' => null, 'days_ago' => 12],
                ],
            ],
            [
                'full_name' => 'Rania Haddad',
                'email' => 'rania.sample@example.test',
                'phone' => '+962790000003',
                'type' => Lead::TYPE_CONTACT,
                'source_platform' => 'behance',
                'landing_page_url' => '/contact',
                'message' => 'Sample data — general enquiry about the foreign investment licence. '.DemoDataSeeder::SAMPLE_MARKER,
            ],
            [
                'full_name' => 'فيصل الدوسري',
                'email' => 'faisal.sample@example.test',
                'phone' => '+966500000004',
                'type' => Lead::TYPE_ESTIMATE,
                'source_platform' => 'organic',
                'landing_page_url' => '/estimator',
                'message' => 'بيانات عينة — تقدير تكلفة عبر الحاسبة. '.DemoDataSeeder::SAMPLE_MARKER,
                'conversions' => [
                    ['type' => 'payment_received', 'value' => 21500.00, 'days_ago' => 5],
                ],
            ],
            [
                'full_name' => 'هيا الزهراني',
                'email' => 'haya.sample@example.test',
                'phone' => '+966500000005',
                'type' => Lead::TYPE_CONSULTATION,
                'source_platform' => 'referral',
                'landing_page_url' => '/packages',
                'message' => 'بيانات عينة — استفسار عن باقة النمو. '.DemoDataSeeder::SAMPLE_MARKER,
            ],
        ];
    }
}
