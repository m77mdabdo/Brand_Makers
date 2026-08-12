<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestones logged against a lead, and the money attached to them.
 *
 * `value` is nullable because not every conversion is monetary — a
 * "qualified" or "meeting_booked" milestone is worth recording on its own.
 *
 * `occurred_at` is deliberately NOT NULL with NO default. MySQL silently
 * supplies current_timestamp() for the first TIMESTAMP column in a table,
 * but SQLite (which the test suite runs on) does not, so a write that
 * omits it fails there and passes here — the worst kind of difference. An
 * event always happened at a knowable time, so every writer sets it
 * explicitly rather than having a default quietly record "now" for a
 * backdated entry. It is a `datetime` rather than a `timestamp` for the
 * same reason the visitor_sessions columns are.
 *
 * lead_id is nullOnDelete, and Lead soft-deletes, so an event attached to
 * an archived lead keeps its foreign key — which is why
 * ConversionEvent::lead() resolves withTrashed().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversion_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->decimal('value', 12, 2)->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('url')->nullable();
            $table->json('utm_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            // Reporting reads this table by type and by date range.
            $table->index('event_type', 'conversion_events_event_type_index');
            $table->index('occurred_at', 'conversion_events_occurred_at_index');
            // A lead's timeline, newest first.
            $table->index(['lead_id', 'occurred_at'], 'conversion_events_lead_occurred_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_events');
    }
};
