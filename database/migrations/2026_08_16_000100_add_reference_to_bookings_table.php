<?php

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The reference a visitor is given on the confirmation screen.
 *
 * WHY NOT JUST THE ID. The id is sequential, so quoting it tells anyone
 * who books how many appointments the studio has taken and lets them guess
 * their neighbours' — and "your booking is #7" reads like a bug report
 * rather than a confirmation. This mirrors orders.reference exactly:
 * CM-YYYY-XXXXXX from a crypto-secure source over an alphabet with no 0/O
 * or 1/I, because this is a string people read out over the phone.
 *
 * ADDED IN THREE STEPS, which is what makes it safe on a table that may
 * already hold rows:
 *   1. add it nullable, so the ALTER cannot fail on existing bookings;
 *   2. backfill every existing row with its own generated reference;
 *   3. only then make it NOT NULL and unique.
 *
 * A single non-nullable ALTER would abort on any existing booking, and
 * adding the unique index before the backfill would collide on the second
 * NULL under some engines. down() drops the column, so the rollback is a
 * plain reversal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reference', 32)->nullable()->after('id');
        });

        // Row by row rather than one expression: the reference comes from
        // random_int() and has to be unique, which is not something SQL can
        // generate for us portably.
        DB::table('bookings')->whereNull('reference')->orderBy('id')->each(function (object $booking) {
            DB::table('bookings')
                ->where('id', $booking->id)
                ->update(['reference' => Booking::generateReference()]);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reference', 32)->nullable(false)->change();
            $table->unique('reference', 'bookings_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // The index goes with the column on every engine, but dropping
            // it explicitly first keeps the rollback identical on SQLite,
            // which rebuilds the table rather than altering it.
            $table->dropUnique('bookings_reference_unique');
            $table->dropColumn('reference');
        });
    }
};
