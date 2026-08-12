<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('flag_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('name');
            $table->json('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'countries_is_active_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
