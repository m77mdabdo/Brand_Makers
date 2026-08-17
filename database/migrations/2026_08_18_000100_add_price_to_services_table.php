<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A price on the service itself, so there is ONE place a service costs
 * something.
 *
 * WHY HERE AND NOT AS AN ESTIMATOR ITEM. The estimator used to carry its
 * own copy of the eight formation services as line items, which meant the
 * catalogue existed twice: rename a service on /services and the
 * estimator kept the old wording, price it in one place and the other
 * stayed silent. The estimator now reads services directly, and this is
 * the column it reads.
 *
 * NULLABLE, and null means "على الطلب" — the same meaning it carries on
 * packages and on estimator items. It is a real answer, not a missing
 * one: a service with no agreed figure is still offered, still
 * selectable, and turns a quote into a floor rather than contributing a
 * number. 0.00 stays available and distinct, meaning free.
 *
 * `currency` mirrors estimator_items: a per-row column with a default, so
 * a mixed-currency selection is DETECTABLE (EstimatorService refuses to
 * total one rather than inventing a conversion rate) even though the
 * catalogue is single-currency today.
 *
 * ROLLBACK-SAFE. down() drops only the two columns this added; nothing
 * else references them, and no data outside them is touched. Prices
 * entered by an admin are lost on rollback, which is inherent to dropping
 * the column that holds them and is why down() is not something to run
 * casually on a live install.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->after('is_flagship');
            $table->string('currency', 3)->default('SAR')->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['price', 'currency']);
        });
    }
};
