<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an estimator item say "على الطلب" instead of being hidden.
 *
 * UNTIL NOW `price` was NOT NULL, so "nobody has priced this yet" could
 * only be expressed as is_active = false — the item vanished from the
 * form entirely. That was the honest choice while the alternative was
 * quoting zero, but it meant a fresh install showed an estimator with no
 * options in it, and an admin had to price every line before a visitor
 * could see any of them.
 *
 * Nullable brings items into line with packages, where null has always
 * meant "on request" and is a different fact from 0.00 (free). An
 * unpriced item is now SELECTABLE: it appears, it is labelled on request,
 * and choosing it turns the quote into a floor rather than a total —
 * exactly what already happens when a priced-on-request package is
 * chosen. Nothing is invented, and nothing is hidden.
 *
 * is_active keeps its own meaning: withdrawn from sale, price or no price.
 *
 * REVERSIBLE WITH ONE CAVEAT, stated rather than hidden. down() has to
 * restore NOT NULL, and a NULL cannot survive that — so rows priced on
 * request are set to 0.00 first. That is lossy: 0.00 reads as free. The
 * rollback also deactivates exactly those rows, so nothing that lost its
 * "on request" meaning is left selectable at a price of zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimator_items', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Deactivate before flattening, so no row survives as "free".
        DB::table('estimator_items')->whereNull('price')->update([
            'price' => 0,
            'is_active' => false,
        ]);

        Schema::table('estimator_items', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable(false)->change();
        });
    }
};
