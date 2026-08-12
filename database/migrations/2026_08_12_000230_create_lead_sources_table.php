<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The channel vocabulary attribution resolves against.
 *
 * `key` is unique because leads.source_platform stores this row's KEY as a
 * plain string rather than a foreign key — a lead can carry a raw
 * utm_source matching no configured source at all, so a hard FK would
 * reject perfectly real traffic. The uniqueness constraint is what makes
 * that string→row resolution unambiguous.
 *
 * `is_system` marks the seeded sources so the CRUD can refuse to delete
 * them: deleting a source that leads already point at silently strands
 * those leads with a value that no longer resolves in reporting. It is
 * guarded out of $fillable for the same reason as menu_items.is_system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'lead_sources_is_active_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_sources');
    }
};
