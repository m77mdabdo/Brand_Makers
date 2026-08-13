<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an enquiry record WHICH package it came from.
 *
 * A separate column rather than reusing `requested_service_id`: a service
 * and a package are different catalogues with different tables, and
 * overloading one FK would make "which service was asked about" and
 * "which package was chosen" indistinguishable in reporting.
 *
 * nullOnDelete, and Package soft-deletes — so retiring a package leaves
 * the enquiries it produced intact, and Lead::package() resolves
 * withTrashed() so a historical lead still names what was chosen.
 *
 * This is the one ALTER in the schema. Everything else was declared
 * inside its CREATE TABLE, and the down() here drops exactly what the
 * up() added, so the two-cycle rollback guarantee still holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('package_id')
                ->nullable()
                ->after('requested_service_id')
                ->constrained('packages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // dropForeign before dropColumn: MySQL refuses to drop a
            // column that still backs a foreign key constraint.
            $table->dropForeign(['package_id']);
            $table->dropColumn('package_id');
        });
    }
};
