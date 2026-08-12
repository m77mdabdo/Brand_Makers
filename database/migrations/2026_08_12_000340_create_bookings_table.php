<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consultation bookings taken from the public site.
 *
 * `scheduled_at` is one `datetime` holding the START of the booked slot,
 * not a separate date column plus a slot column. The slot's length comes
 * from booking_availability for that weekday, so storing it again here
 * would let the two disagree — and a single instant is what every query
 * this table serves ("today's bookings", "is 10:00 full", "upcoming")
 * actually compares on. It is `datetime` rather than `timestamp` for the
 * same reason as elsewhere in this schema: MySQL gives the second TIMESTAMP
 * column in a table an implicit invalid default, and these are app-managed
 * values that want no implicit DEFAULT or ON UPDATE behaviour at all.
 *
 * `status` is guarded out of $fillable. A booking that could confirm
 * itself from a form payload would let an unauthenticated visitor skip
 * the review step entirely, so it is only ever moved by
 * Booking::markConfirmed() and friends.
 *
 * NO UNIQUE INDEX ON scheduled_at, deliberately: capacity is per-slot and
 * configurable (booking_availability.capacity), and cancelled bookings
 * must not hold a slot hostage. "At most N live rows for this instant" is
 * not expressible as a DB constraint, so it is enforced where the count
 * can exclude cancelled rows — see Booking::scopeHoldingSlot(). The
 * composite index below is what makes that check one indexed read.
 *
 * Both foreign keys are nullOnDelete and point at soft-deleting models: a
 * booking is a historical record, so archiving a service or a lead must
 * leave it standing rather than deleting the appointment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();

            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();

            $table->dateTime('scheduled_at');

            $table->string('status')->default('pending');
            $table->text('notes')->nullable();

            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // The capacity check and the day/agenda views all read
            // "bookings at (or around) this instant, excluding cancelled".
            $table->index(['scheduled_at', 'status'], 'bookings_scheduled_at_status_index');
            // The dashboard queue: everything pending, soonest first.
            $table->index(['status', 'scheduled_at'], 'bookings_status_scheduled_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
