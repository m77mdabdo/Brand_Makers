<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-portal orders: what a client bought and how far along it is.
 *
 * TWO IDENTIFIERS, TWO JOBS.
 *
 *   reference          human-shareable, unique, printed on invoices and
 *                      quoted over the phone ("CM-2026-7QK4TP"). It is
 *                      NOT a credential: it is short enough to read aloud,
 *                      which is precisely why it must never be sufficient
 *                      to open an order.
 *   tracking_code_hash the SHA-256 of a 256-bit code generated from
 *                      random_bytes(). The plaintext is shown to the
 *                      client ONCE, at creation, and is never stored — so
 *                      a database dump yields no working portal links, and
 *                      staff with table access cannot read a client's
 *                      order.
 *
 * WHY SHA-256 AND NOT BCRYPT. Password hashes are slow on purpose because
 * passwords are low-entropy and therefore guessable. This code carries a
 * full 256 bits from a CSPRNG, so there is nothing to brute-force and the
 * slow hash would only buy a per-request cost. A plain digest also keeps
 * lookup to one indexed equality read, which is what makes the portal
 * resistant to enumeration: the presented code is hashed and matched
 * directly, so there is no scan, no per-row comparison, and no timing
 * signal that distinguishes "no such order" from "wrong code".
 *
 * `reference`, `status`, `progress` and `tracking_code_hash` are all
 * guarded out of $fillable. An order that could set its own status or
 * progress from a request payload would let a client mark their own work
 * complete; one that could set its own tracking code would let an attacker
 * choose a known value and then read the order back.
 *
 * user_id is the optional portal account. It stays nullable because the
 * common case is a client who has an order and a tracking code but has
 * never registered — requiring an account to have an order would invert
 * the portal's purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 32)->unique();

            // 64 hex chars. char() rather than varchar(): every value is
            // exactly this length, so the fixed width is free and honest.
            $table->char('tracking_code_hash', 64)->unique();

            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();

            $table->string('status')->default('pending');

            // 0–100. unsignedTinyInteger caps at 255, so the column itself
            // rejects a negative or wildly out-of-range write; the 0–100
            // bound is enforced in Order::setProgress().
            $table->unsignedTinyInteger('progress')->default(0);

            $table->decimal('total', 12, 2)->nullable();
            $table->string('currency', 3)->default('SAR');

            $table->text('notes')->nullable();
            $table->dateTime('placed_at');
            $table->dateTime('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // The dashboard queue: open orders, oldest first.
            $table->index(['status', 'placed_at'], 'orders_status_placed_at_index');
            $table->index('user_id', 'orders_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
