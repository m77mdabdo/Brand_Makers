<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value site configuration edited from the dashboard.
 *
 * `value` is JSON rather than a string so one column can hold a scalar, a
 * boolean, a number, or a per-locale map ({"ar": "...", "en": "..."})
 * without a second "type" column to interpret it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group');
            $table->timestamps();

            // The editor renders one panel per group.
            $table->index('group', 'settings_group_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
