<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Article comments, moderated before they appear.
 *
 * `status` is a plain string with a default rather than a DB-level ENUM —
 * the same convention used for leads.type, bookings.status and
 * orders.status here, so the vocabulary lives in one place in PHP
 * (Comment::STATUSES) and the schema stays portable between MySQL and the
 * SQLite the test suite runs on.
 *
 * `ip_address` holds a TRUNCATED address, never the raw one — the column
 * is written through Comment's ip_address mutator, which routes every
 * value through IpAnonymizer. It is 45 chars because a truncated IPv6
 * prefix is still an IPv6-shaped string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->text('body');
            $table->string('status')->default('pending');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // The moderation queue reads pending comments newest first.
            $table->index(['status', 'created_at'], 'comments_status_created_at_index');
            // An article's approved comments, in order — the public read.
            $table->index(['article_id', 'status'], 'comments_article_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
