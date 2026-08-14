<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a visitor was quoted by the cost estimator, stored on the lead it
 * produced.
 *
 * TWO COLUMNS, NOT ONE, and the split is the whole point.
 *
 * `estimate_total` is a real decimal column because the business sorts,
 * filters and eventually sums on it — "show me estimates above 20,000" is
 * a question a JSON path answers badly and an indexed column answers well.
 * Nullable, because an estimate whose every line is priced-on-request has
 * no total, and 0.00 would be a different and wrong claim.
 *
 * `estimate_snapshot` is the itemised breakdown, copied VERBATIM at
 * submission time — each line's key, label and price as they were on the
 * day. It deliberately duplicates data that lives in estimator_items,
 * because those prices change and the labels get rewritten: a lead has to
 * keep showing what this visitor was actually shown, not what the
 * catalogue says today. Referencing the items by key alone would produce a
 * breakdown that silently rewrites itself after the next price rise.
 *
 * Both are nullable and neither is on a non-estimate lead. This is the
 * SECOND alter on `leads` (the first added package_id); like that one it
 * is additive, so down() is a plain drop and the rollback is clean.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->decimal('estimate_total', 12, 2)->nullable()->after('package_id');
            $table->json('estimate_snapshot')->nullable()->after('estimate_total');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['estimate_total', 'estimate_snapshot']);
        });
    }
};
