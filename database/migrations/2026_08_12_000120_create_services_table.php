<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The service catalogue.
 *
 * Soft-deletes, because leads and bookings reference a service and an
 * archived service must not erase what an existing enquiry was about.
 * Note the consequence: the unique index on `slug` still counts
 * soft-deleted rows, which is why HasSlug checks uniqueness withTrashed().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_flagship')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('name');
            $table->json('summary');
            $table->json('body');
            $table->json('requirements');
            $table->json('process');
            $table->timestamps();
            $table->softDeletes();

            // Read on effectively every public request: active services in
            // display order.
            $table->index(['is_active', 'sort_order'], 'services_is_active_sort_order_index');
            $table->index('is_flagship', 'services_is_flagship_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
