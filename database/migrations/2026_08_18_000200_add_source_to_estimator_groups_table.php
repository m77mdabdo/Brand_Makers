<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a group's options come from.
 *
 * 'items' — the default and what every group did until now — means the
 * options are estimator_items rows.
 *
 * 'services' means the options ARE the service catalogue, read live from
 * the services table with their own prices. The estimator used to carry
 * its own copy of the eight formation services as line items, so the
 * catalogue existed twice: renaming a service on /services left the
 * estimator showing the old wording, and pricing one in either place left
 * the other silent. One list, one price, two surfaces.
 *
 * A COLUMN RATHER THAN A HARDCODED GROUP KEY, so the services selector
 * stays as admin-managed as the others: it can be renamed, reordered,
 * switched to single-choice or hidden entirely from the same screen, and
 * a future source (packages, say) needs a value here rather than a branch
 * in the view.
 *
 * Rollback-safe: down() drops only this column, and every group falls
 * back to 'items', which is what they all were before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimator_groups', function (Blueprint $table) {
            $table->string('source')->default('items')->after('selection');
        });
    }

    public function down(): void
    {
        Schema::table('estimator_groups', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
