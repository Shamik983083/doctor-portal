<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1a: Shared product data model — offering_partner pivot.
 *
 * Each row grants a storefront (partner) access to an offering.
 * Offerings may be shared across multiple storefronts; before this migration
 * each offering had exactly one owner via offerings.partner_id (FK).
 *
 * The pivot is seeded from that existing FK so every currently-owned offering
 * immediately appears in the new access table and existing case_offerings rows
 * remain valid.
 *
 * offerings.partner_id is NOT dropped here — it is made nullable in the
 * next migration and removed in a follow-up after Phase 2 confirms all
 * readers use the pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offering_partner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offering_id')->constrained('offerings')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();

            // Per-storefront SIG override. Null means "use the global sig on offerings.sig".
            $table->text('sig_override')->nullable();

            // Allows revoking access without deleting the row (audit trail preserved).
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['offering_id', 'partner_id']);
            $table->index(['partner_id', 'is_active']);
        });

        // Seed pivot from existing ownership data so no access is lost.
        // IGNORE duplicates in case the migration is re-run against partial state.
        DB::statement("
            INSERT IGNORE INTO offering_partner (offering_id, partner_id, is_active, created_at, updated_at)
            SELECT id, partner_id, 1, NOW(), NOW()
            FROM offerings
            WHERE partner_id IS NOT NULL
              AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('offering_partner');
    }
};
