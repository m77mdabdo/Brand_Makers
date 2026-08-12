<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every enquiry the public site produces, whatever form it came from.
 *
 * TWO CAMPAIGN COLUMNS, ON PURPOSE.
 *
 *   campaign_id         raw external ad-platform string, captured from the
 *                       landing URL. NOT a foreign key: it lives in a
 *                       different identifier space, and a lead can
 *                       legitimately carry an id for a campaign nobody has
 *                       created a record for yet. Never modified after
 *                       capture — it is the source of truth for "what did
 *                       the ad platform tell us".
 *   linked_campaign_id  resolved internal FK, set when that external id
 *                       matches a Campaign record. nullOnDelete, so
 *                       removing a campaign keeps its historical leads
 *                       intact rather than deleting the enquiries it
 *                       produced.
 *
 * Collapsing the two would break attribution capture; making campaign_id
 * an FK would reject real traffic.
 *
 * requested_service_id is nullOnDelete for the same reason, and Service
 * soft-deletes — which is why Lead::requestedService() resolves
 * withTrashed(): the enquiry must keep showing what it was about after the
 * service is archived.
 *
 * `phone` is nullable: the consultation form requires it, the contact form
 * does not, and one table serves both.
 *
 * `is_handled` is the inbox read-state. Guarded out of $fillable — it is
 * never part of a public submission, and letting a form payload mark its
 * own enquiry as already dealt with would hide it from the inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('email')->nullable();
            $table->string('nationality')->nullable();
            $table->string('country_of_residence')->nullable();

            $table->foreignId('requested_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('requested_activity')->nullable();
            $table->boolean('owns_external_company')->default(false);
            $table->text('message')->nullable();

            $table->string('type')->default('consultation');
            $table->boolean('is_handled')->default(false);

            // --- attribution, written once at capture ---
            $table->string('source_platform')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('campaign_id')->nullable();
            $table->foreignId('linked_campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('adset_name')->nullable();
            $table->string('adset_id')->nullable();
            $table->string('ad_name')->nullable();
            $table->string('ad_id')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('landing_page_url')->nullable();
            $table->string('referrer_url')->nullable();
            $table->string('gclid')->nullable();
            $table->string('fbclid')->nullable();
            $table->string('ttclid')->nullable();
            $table->json('first_touch')->nullable();
            $table->json('latest_touch')->nullable();

            // Consent is proven per lead, since every public form here
            // collects a phone number or an email address.
            $table->boolean('consent_given')->default(false);
            $table->timestamp('consented_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // The dashboard lists leads by type, newest first.
            $table->index(['type', 'created_at'], 'leads_type_created_at_index');
            // The unread badge runs on EVERY dashboard page render.
            $table->index(['type', 'is_handled'], 'leads_type_is_handled_index');
            // Reporting groups leads by channel.
            $table->index('source_platform', 'leads_source_platform_index');
            $table->index('created_at', 'leads_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
