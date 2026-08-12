<?php

namespace Database\Seeders;

use App\Models\BookingAvailability;
use Illuminate\Database\Seeder;

/**
 * The default weekly booking window. Real configuration, not sample data:
 * the standard Gulf working week, Sunday to Thursday, 09:00–17:00, with
 * Friday and Saturday closed.
 *
 * Every weekday gets a row — including the closed ones — so the dashboard
 * shows a complete week to edit rather than a form with two days missing.
 *
 * Idempotent on day_of_week, and an EXISTING row is left completely alone:
 * re-seeding must not quietly reset opening hours an admin has adjusted.
 */
class BookingAvailabilitySeeder extends Seeder
{
    /**
     * The Gulf weekend. Kept as a named list rather than inline magic
     * numbers so the intent survives someone reading this in six months.
     *
     * @var list<int>
     */
    private const WEEKEND = [BookingAvailability::FRIDAY, BookingAvailability::SATURDAY];

    public function run(): void
    {
        for ($day = 0; $day <= 6; $day++) {
            if (BookingAvailability::where('day_of_week', $day)->exists()) {
                continue;
            }

            BookingAvailability::create([
                'day_of_week' => $day,
                'is_open' => ! in_array($day, self::WEEKEND, true),
                'opens_at' => '09:00:00',
                'closes_at' => '17:00:00',
                'slot_minutes' => 45,
                'capacity' => 1,
            ]);
        }
    }
}
