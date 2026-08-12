<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editorial pages. Every human-readable field is a translatable JSON
 * column (spatie/laravel-translatable), keyed by locale.
 *
 * `slug` is unique because it is the route key — see Page::getRouteKeyName.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->boolean('is_published')->default(true);
            $table->json('title');
            $table->json('body');
            $table->json('meta_title');
            $table->json('meta_description');
            $table->timestamps();

            // The public router looks pages up by slug filtered on
            // published state; the dashboard index sorts on it.
            $table->index('is_published', 'pages_is_published_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
