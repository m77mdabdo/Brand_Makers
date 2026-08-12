<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The status timeline a client sees in the portal.
 *
 * cascadeOnDelete, unlike most foreign keys in this schema: an event is
 * not a historical record in its own right, it is a line in one order's
 * history and is meaningless without it. Orders soft-delete, so the
 * cascade only fires on a genuine hard delete.
 *
 * `occurred_at` is NOT NULL with no default, for the same reason as
 * conversion_events.occurred_at: MySQL would silently supply "now" while
 * SQLite rejects the omission, and a timeline entry backdated to a real
 * event must not be quietly stamped with the moment it was typed in.
 *
 * `status` mirrors the order status this entry recorded, rather than being
 * derived by reading the previous row — a timeline must stay truthful
 * about what was said at the time even if the status vocabulary is later
 * extended.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('status');
            $table->json('note')->nullable();
            $table->dateTime('occurred_at');

            $table->timestamps();

            // The only read: one order's timeline, in order. Leading with
            // order_id also backs the foreign key, so MySQL adds no second
            // redundant index for it.
            $table->index(['order_id', 'occurred_at'], 'order_events_order_occurred_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
