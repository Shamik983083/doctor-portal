<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1a: Make offerings.partner_id nullable.
 *
 * The column is kept so all existing queries that filter by partner_id continue
 * to work without change. Making it nullable allows offerings to be shared
 * across storefronts (no single owner) once the codebase is fully migrated to
 * the offering_partner pivot.
 *
 * FOLLOW-UP (post Phase 2): once all readers are confirmed on the pivot,
 * run a separate migration to drop this column entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offerings', function (Blueprint $table) {
            // Drop the existing NOT NULL FK constraint, then re-add as nullable.
            $table->dropForeign(['partner_id']);
            $table->unsignedBigInteger('partner_id')->nullable()->change();
            $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Reverse: make partner_id NOT NULL again (only safe if all rows have a value).
        Schema::table('offerings', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->unsignedBigInteger('partner_id')->nullable(false)->change();
            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
        });
    }
};
