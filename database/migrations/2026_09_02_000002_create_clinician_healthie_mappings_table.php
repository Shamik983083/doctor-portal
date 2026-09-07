<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinician_healthie_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinician_id')->constrained('clinicians')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();

            // The Healthie user ID for this clinician in this specific sub-org.
            // A global clinician has one row per partner, each with a different
            // healthie_user_id (Healthie creates a separate user record per sub-org).
            $table->string('healthie_user_id')->nullable();

            // Denormalised from partner_ehr_settings.organization_id for fast lookup
            // without a join at prescription time.
            $table->string('healthie_org_id')->nullable();

            $table->string('status')->default('pending'); // pending | synced | failed
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // One row per clinician per storefront — idempotency key.
            $table->unique(['clinician_id', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinician_healthie_mappings');
    }
};
