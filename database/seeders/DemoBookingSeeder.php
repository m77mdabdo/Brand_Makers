<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingBlackoutDate;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * FABRICATED consultation bookings. Loaded only via DemoDataSeeder — these
 * are appointments nobody made, and seeding them in production would show
 * the studio a calendar of meetings that do not exist.
 *
 * Covers every status so the dashboard's filters have something to filter,
 * including a cancelled one at the same instant as a confirmed booking,
 * which is what demonstrates that a cancelled booking releases its slot.
 *
 * Idempotent on the sample email + scheduled_at pair.
 */
class DemoBookingSeeder extends Seeder
{
    public function run(): void
    {
        $service = Service::orderBy('sort_order')->first();
        $lead = Lead::where('email', 'khalid.sample@example.test')->first();

        // A slot boundary in the future, on a working day, so the demo
        // rows sit where the availability config would actually place them.
        $slot = now()->addWeek()->startOfDay()->setTime(10, 0);

        while (in_array($slot->dayOfWeek, [5, 6], true)) {
            $slot->addDay();
        }

        foreach ($this->bookings() as $index => $definition) {
            $scheduledAt = (clone $slot)->addDays($definition['day_offset'])->addMinutes($definition['minute_offset']);

            $booking = Booking::updateOrCreate(
                [
                    'email' => $definition['email'],
                    'scheduled_at' => $scheduledAt,
                ],
                [
                    'name' => $definition['name'].' '.DemoDataSeeder::SAMPLE_MARKER,
                    'phone' => '+96650000'.str_pad((string) (1000 + $index), 4, '0', STR_PAD_LEFT),
                    'service_id' => $service?->id,
                    'lead_id' => $definition['link_lead'] ?? false ? $lead?->id : null,
                    'notes' => 'بيانات عينة — حجز تجريبي. '.DemoDataSeeder::SAMPLE_MARKER,
                ]
            );

            // status is guarded, so it moves through the model's own
            // transitions rather than being mass-assigned.
            match ($definition['status']) {
                Booking::STATUS_CONFIRMED => $booking->markConfirmed(),
                Booking::STATUS_COMPLETED => $booking->markCompleted(),
                Booking::STATUS_CANCELLED => $booking->markCancelled(),
                default => null,
            };
        }

        // One blacked-out date, so the slot generator has something to
        // exclude and the dashboard screen is not empty.
        BookingBlackoutDate::updateOrCreate(
            ['date' => (clone $slot)->addDays(10)->toDateString()],
            ['reason' => ['ar' => 'إجازة الفريق '.DemoDataSeeder::SAMPLE_MARKER, 'en' => 'Team holiday '.DemoDataSeeder::SAMPLE_MARKER]]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bookings(): array
    {
        return [
            ['name' => 'خالد المطيري', 'email' => 'khalid.sample@example.test', 'status' => Booking::STATUS_CONFIRMED, 'day_offset' => 0, 'minute_offset' => 0, 'link_lead' => true],
            ['name' => 'نورة العتيبي', 'email' => 'noura.sample@example.test', 'status' => Booking::STATUS_PENDING, 'day_offset' => 0, 'minute_offset' => 45],
            // Same instant as the confirmed booking above: a cancelled row
            // must NOT hold the slot, which is exactly what
            // Booking::scopeHoldingSlot() encodes.
            ['name' => 'Rania Haddad', 'email' => 'rania.sample@example.test', 'status' => Booking::STATUS_CANCELLED, 'day_offset' => 0, 'minute_offset' => 0],
            ['name' => 'فيصل الدوسري', 'email' => 'faisal.sample@example.test', 'status' => Booking::STATUS_PENDING, 'day_offset' => 1, 'minute_offset' => 0],
            ['name' => 'هيا الزهراني', 'email' => 'haya.sample@example.test', 'status' => Booking::STATUS_COMPLETED, 'day_offset' => -8, 'minute_offset' => 90],
        ];
    }
}
