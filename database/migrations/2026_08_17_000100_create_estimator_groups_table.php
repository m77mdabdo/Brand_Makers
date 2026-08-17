<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groups for the cost estimator, so the admin owns them.
 *
 * WHAT WAS WRONG WITH THE OLD ARRANGEMENT. `estimator_items.group` is a
 * free-form slug the admin can type, which was the right call — a new
 * group should not need a deployment. But the group's LABEL lived in
 * the site lang files under site.estimator.groups, so a group the admin
 * invented rendered as its raw slug on the public page. Half the feature
 * was manageable and half was not, and the half that was not was the half
 * visitors read.
 *
 * The label now lives here, translatable, beside the two other things a
 * group needs to render: its position, and whether picking from it is
 * exclusive.
 *
 * `selection` is what turns a flat checkbox list into the grouped
 * selectors this screen wants — "نوع التأسيس" admits one answer, "خدمات
 * أخرى" admits several. It is a column rather than a convention so the
 * admin can change a group's behaviour without touching code.
 *
 * NO FOREIGN KEY to estimator_items.group, deliberately. The two are
 * joined on a slug the admin types, and a hard constraint would mean an
 * item could not exist while its group was being renamed, or that
 * deleting a group deleted priced items with it. An item whose group has
 * no row still renders — under its raw slug, exactly as before — so the
 * failure mode is cosmetic rather than data loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimator_groups', function (Blueprint $table) {
            $table->id();

            // The slug estimator_items.group points at. Unique, because
            // two groups answering to one name would make the join
            // ambiguous and the render order arbitrary.
            $table->string('key')->unique();

            $table->json('label');

            // 'single' renders a <select>, 'multi' a multi-select. Stored
            // as a string rather than a boolean so a third mode (a
            // quantity picker, say) does not need a migration to express.
            $table->string('selection')->default('multi');

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // The public catalogue reads active groups in order on every
            // estimator render; this is the whole query.
            $table->index(['is_active', 'sort_order'], 'estimator_groups_active_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimator_groups');
    }
};
