<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The data-driven public navigation.
 *
 * parent_id is self-referencing with nullOnDelete, which encodes the
 * no-orphan rule at the DB level: deleting a parent REPARENTS its children
 * to top level rather than deleting them or leaving them pointing at a row
 * that no longer exists. Non-destructive, and an admin who removes a
 * dropdown keeps every child as a normal top-level item.
 *
 * route_name is not a foreign key to anything — it holds a Laravel route
 * name, validated against MenuItem::ROUTE_WHITELIST (parameter-free public
 * routes only) so a menu row can never resolve to a route that needs a
 * bound model and 500 the navbar on every page of the site.
 *
 * `is_system` marks the seeded core nav. It is guarded out of $fillable:
 * an admin-created item must never be able to claim system status through
 * a form payload, because system status is what protects a row from
 * deletion.
 *
 * `key` is the stable identifier for those seeded items, and the unique
 * constraint the seeder matches on. It is NULLABLE and left null for
 * admin-created items — deliberately, so the constraint never blocks the
 * legitimate case of two menu entries pointing at the same destination
 * (one top-level, one inside a dropdown). NULLs do not collide in a unique
 * index on either MySQL or SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('menu_items')
                ->nullOnDelete();

            $table->string('key')->nullable()->unique();

            $table->json('label');

            // route | url | none  ('none' = a parent that only opens a
            // dropdown and has no destination of its own).
            $table->string('link_type', 16)->default('route');
            $table->string('route_name')->nullable();
            $table->string('url')->nullable();

            $table->string('target', 8)->default('_self');

            $table->boolean('is_visible')->default(true);
            $table->boolean('is_system')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // The public navbar reads exactly this: visible items, by
            // parent, in order. Leading with parent_id also backs the
            // self-referencing foreign key, so MySQL adds no duplicate.
            $table->index(['parent_id', 'is_visible', 'sort_order'], 'menu_items_tree_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
