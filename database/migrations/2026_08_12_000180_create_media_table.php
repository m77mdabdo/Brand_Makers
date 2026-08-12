<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The media library.
 *
 * uploaded_by is nullOnDelete rather than cascade: removing a staff
 * account must not delete the site's images along with it. The file
 * outlives the person who uploaded it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('disk')->default('public');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('type');
            $table->string('alt_text')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The library filters by kind and shows newest first.
            $table->index(['type', 'created_at'], 'media_type_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
