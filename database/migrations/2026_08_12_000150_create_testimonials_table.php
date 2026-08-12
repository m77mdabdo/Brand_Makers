<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client quotes. Only `quote` is translatable — a person's name and job
 * title are not translated, they are transliterated at most, and storing
 * two spellings of the same human invites them to drift apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('client_title')->nullable();
            $table->string('client_country')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('quote');
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'testimonials_is_active_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
