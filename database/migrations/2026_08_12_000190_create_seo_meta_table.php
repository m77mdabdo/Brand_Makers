<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-record SEO overrides, attached polymorphically.
 *
 * seo_metable_type stores a MORPH ALIAS ('page', 'article', …), never a
 * fully-qualified class name — AppServiceProvider calls
 * Relation::enforceMorphMap(), which both shortens the stored value and
 * makes an unmapped model a loud error instead of a silent write. That
 * matters here because these rows outlive refactors: a class rename would
 * otherwise orphan every row pointing at the old namespace.
 *
 * The unique constraint is the actual invariant — one SEO record per
 * owner. Without it, updateOrCreate races could leave a record with two
 * competing meta titles and no rule for which one renders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->morphs('seo_metable');
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->timestamps();

            $table->unique(['seo_metable_type', 'seo_metable_id'], 'seo_meta_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
