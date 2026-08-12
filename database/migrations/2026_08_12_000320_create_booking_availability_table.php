<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The weekly availability config the booking slot generator reads:
 * working hours, slot length and per-slot capacity, one row per weekday.
 *
 * WHY A TABLE AND NOT SETTINGS ROWS. Slot length and capacity genuinely
 * vary by day (a short Thursday, a busier Sunday), so folding them into a
 * single settings blob would either flatten that or reinvent a per-day
 * structure inside JSON that nothing can index or constrain. Seven rows
 * with a unique key per weekday says the same thing and lets the database
 * enforce "exactly one config per day".
 *
 * `day_of_week` is 0 = Sunday … 6 = Saturday, matching Carbon::dayOfWeek,
 * so no translation layer sits between the config and the date maths. The
 * unique index is the real invariant: two rows for Monday would make "what
 * time do we open" ambiguous.
 *
 * `opens_at`/`closes_at` are TIME columns — a wall-clock time of day with
 * no date attached is exactly what they are, and storing them as strings
 * would defeat any ordering comparison.
 *
 * `capacity` is per generated slot, not per day: two people can hold the
 * same 10:00 consultation when capacity is 2. A booking is rejected when
 * the count of live bookings for that instant reaches it — enforced in the
 * booking service, since no DB constraint can express "at most N rows
 * sharing this value, excluding cancelled ones".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_availability', function (Blueprint $table) {
            $table->id();

            $table->unsignedTinyInteger('day_of_week')->unique();
            $table->boolean('is_open')->default(true);

            $table->time('opens_at')->default('09:00:00');
            $table->time('closes_at')->default('17:00:00');

            $table->unsignedSmallInteger('slot_minutes')->default(30);
            $table->unsignedSmallInteger('capacity')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_availability');
    }
};
