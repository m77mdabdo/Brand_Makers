<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('cover_image')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('body');
            $table->timestamps();
            $table->softDeletes();

            // The blog index runs
            //   where is_published = 1 and published_at <= now()
            //   order by published_at desc
            // — equality column first so the range/sort can ride the same
            // index without a filesort.
            $table->index(['is_published', 'published_at'], 'articles_is_published_published_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
