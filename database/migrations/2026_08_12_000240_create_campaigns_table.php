<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid campaigns, with the figures reporting needs to compute ROI.
 *
 * `external_campaign_id` is unique because it is the lookup key that
 * resolves an incoming lead's raw ad-platform campaign string to an
 * internal Campaign record. Two campaigns sharing one external id would
 * make that match ambiguous, and ROI would then be attributed to whichever
 * row the database happened to return first.
 *
 * It is nullable — a campaign can be tracked manually before anyone wires
 * up its platform id — and NULLs do not collide in a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('platform')->nullable();
            $table->string('external_campaign_id')->nullable()->unique();
            $table->decimal('budget', 12, 2)->nullable();
            $table->decimal('spend', 12, 2)->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'name'], 'campaigns_is_active_name_index');
            $table->index('platform', 'campaigns_platform_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
