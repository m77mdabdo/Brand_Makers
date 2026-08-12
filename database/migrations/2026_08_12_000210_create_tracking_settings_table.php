<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Third-party analytics/pixel IDs.
 *
 * Separate from `settings` on purpose: these values are interpolated into
 * inline <script> blocks, so each one is pinned to its platform's
 * published ID format (TrackingSetting::FORMATS) before it can be stored.
 * Mixing them into the general settings bag would lose that distinction.
 *
 * Everything defaults to inactive, so a fresh install ships with no
 * third-party script on the page until someone deliberately enables one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index('is_active', 'tracking_settings_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_settings');
    }
};
