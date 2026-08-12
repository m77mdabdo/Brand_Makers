<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pricing packages ("الباقات").
 *
 * `price` is decimal(12,2), never a float — money compared or summed as a
 * float drifts, and the pricing table is exactly where that becomes
 * visible. Nullable, because a package may legitimately be "price on
 * request" rather than free; a NULL says "no published price" where 0.00
 * would say "costs nothing".
 *
 * `features` is a JSON array of per-locale strings —
 * [{"ar": "...", "en": "..."}, …] — rather than a child table. The list is
 * a bullet list rendered whole and never queried, filtered or joined
 * against, so a table would add a join to every pricing page render and
 * buy nothing.
 *
 * Soft-deletes, because orders reference a package and archiving one must
 * not erase what a past client actually bought.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();

            $table->json('name');
            $table->json('description')->nullable();

            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 3)->default('SAR');

            $table->json('features')->nullable();

            $table->boolean('is_popular')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // The pricing page read: active packages in display order.
            $table->index(['is_active', 'sort_order'], 'packages_is_active_sort_order_index');
            $table->index('is_popular', 'packages_is_popular_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
