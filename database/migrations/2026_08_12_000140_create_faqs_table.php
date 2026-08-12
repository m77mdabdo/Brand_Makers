<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('question');
            $table->json('answer');
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'faqs_is_active_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
