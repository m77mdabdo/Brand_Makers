<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitor session journeys.
 *
 * HOW THIS RELATES TO ATTRIBUTION. It complements it, it does not replace
 * it. Attribution answers "where did this lead come from" and lives on
 * `leads` as flat reporting columns plus two touch snapshots. This table
 * answers a different question — "what did this visitor actually DO" — and
 * most of its rows never become a lead at all. The overlap is deliberate
 * and one-directional: `attribution` here is a SNAPSHOT of the same touch
 * cookies the forms submit, copied in so a session is readable on its own
 * without joining to a lead that may not exist. `leads` remains the source
 * of truth.
 *
 * PRIVACY. There is no raw IP column and no place to put one. `ip_prefix`
 * holds a TRUNCATED address (IPv4 /24, IPv6 /48) and the model routes
 * every write through IpAnonymizer, so the raw value cannot reach the
 * database even by mistake. See that class for why truncation rather than
 * hashing.
 *
 * `started_at` / `last_activity_at` are `datetime`, not `timestamp`: with
 * MySQL's default explicit_defaults_for_timestamp=OFF, the second TIMESTAMP
 * column in a table gets an implicit invalid default and the CREATE fails
 * outright. These are app-managed values that want no implicit DEFAULT or
 * ON UPDATE behaviour at all, so datetime is the honest type as well as
 * the working one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_sessions', function (Blueprint $table) {
            $table->id();

            // The first-party cookie value. Unique because every write is
            // an upsert keyed on it, and it is the only lookup the capture
            // path performs — which is what keeps the per-request cost to
            // one indexed read plus one update.
            $table->string('session_uid', 64)->unique();

            $table->dateTime('started_at')->index();
            $table->dateTime('last_activity_at');

            $table->string('entry_url', 2048);
            $table->string('referrer', 2048)->nullable();

            $table->json('attribution')->nullable();

            // Parsed from the user agent. Three coarse strings rather than
            // the full UA: the UA itself is a fingerprinting surface, and
            // "Mobile / Safari / iOS" is the entire reporting requirement.
            $table->string('device_type', 20)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('platform', 40)->nullable();

            $table->string('ip_prefix', 45)->nullable();
            $table->string('country', 2)->nullable();

            // Ordered array of {type, url, label, ts, duration_ms}. Capped
            // — see VisitorSession::MAX_EVENTS.
            $table->json('events')->nullable();

            // The TRUE page count, which keeps counting after `events`
            // hits its cap, so a capped journey is still recognisable as a
            // long one.
            $table->unsignedInteger('page_count')->default(0);
            $table->unsignedInteger('total_duration_seconds')->default(0);

            // Set when this session submits a form. nullOnDelete rather
            // than cascade: archiving a lead must not silently destroy the
            // journey that produced it.
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();

            $table->timestamps();

            // The index filters on "converted?" (lead_id null or not) and
            // orders by started_at; the standalone started_at index above
            // serves the unfiltered ordering.
            $table->index(['lead_id', 'started_at'], 'visitor_sessions_lead_started_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_sessions');
    }
};
