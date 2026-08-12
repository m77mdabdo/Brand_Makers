<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repeatable blocks belonging to a page.
 *
 * cascadeOnDelete: a section has no meaning without its page, so deleting
 * the page takes its sections with it rather than stranding orphan rows.
 *
 * `content` is a single JSON blob rather than spatie translatable columns
 * — a section's shape varies by key (some carry an icon, some a list), so
 * the locale nesting lives inside the blob and PageSection exposes
 * title/description/icon as locale-aware accessors over it.
 *
 * The composite index leads with page_id and therefore also backs the
 * foreign key, so MySQL creates no second redundant index for it. Because
 * it is declared inside CREATE TABLE (never as a later ALTER), there is no
 * apply-then-rollback path that can leave a duplicate behind — which is
 * exactly the errno 1061 trap this project's schema is built to avoid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->integer('sort_order')->default(0);
            $table->json('content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // The one read the public page performs: this page's active
            // sections, in order. Equality columns first, sort column last,
            // so the index satisfies the WHERE and the ORDER BY together.
            $table->index(['page_id', 'is_active', 'sort_order'], 'page_sections_page_active_sort_index');

            // A page cannot carry the same section key twice.
            $table->unique(['page_id', 'key'], 'page_sections_page_id_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
