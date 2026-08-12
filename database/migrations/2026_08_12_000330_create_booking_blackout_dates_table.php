<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual dates on which nothing can be booked, whatever the weekly
 * availability says — public holidays, Eid, a company offsite.
 *
 * Deliberately date-granular and additive. A closure that recurs weekly
 * belongs in booking_availability (is_open = false for that weekday);
 * this table is for one-off exceptions, and keeping the two apart means
 * neither has to encode the other's rules.
 *
 * `date` is unique because blacking out the same day twice is not a
 * different fact, and the slot generator's "is this date closed" check is
 * a single indexed lookup as a result.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_blackout_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->json('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_blackout_dates');
    }
};
