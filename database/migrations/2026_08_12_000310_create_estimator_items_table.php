<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items for the cost estimator — the visitor ticks some, the total is
 * the sum of their prices.
 *
 * `group` is a plain string rather than a foreign key to a groups table.
 * Groups here are presentational headings for the estimator form ("الهوية
 * البصرية", "المطبوعات"), they carry no attributes of their own, and a
 * lookup table would mean a join on a page that renders every row anyway.
 *
 * `key` is a stable, unique, human-readable identifier that survives a
 * label being rewritten — it is what the seeder matches on so re-seeding
 * updates a row instead of duplicating it, and what a saved estimate would
 * reference. Auto-increment ids would work for the second but not the
 * first.
 *
 * `price` is decimal(12,2) and NOT NULL: an estimator line with no price
 * cannot be summed, so there is no meaningful null here. Use is_active to
 * withdraw an item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimator_items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();

            $table->json('label');
            $table->string('group');

            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('SAR');

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // The estimator renders active items grouped, in order — one
            // indexed read for the whole form.
            $table->index(['is_active', 'group', 'sort_order'], 'estimator_items_active_group_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimator_items');
    }
};
