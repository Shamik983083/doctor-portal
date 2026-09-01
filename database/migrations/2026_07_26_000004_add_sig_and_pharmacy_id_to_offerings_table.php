<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1a: Add global SIG and structured pharmacy FK to offerings.
 *
 * offerings.sig  — global default SIG (directions) for this product.
 *                  The prescribe form reads: offering_partner.sig_override ?? offering.sig.
 *                  Existing records have directions in offerings.directions (patient-facing
 *                  copy on the offering card); sig is the clinical prescription direction.
 *
 * offerings.pharmacy_id — structured FK to the pharmacies table.
 *                         Replaces the loose offerings.pharmacy_name free-text over time.
 *                         Made nullable so existing rows are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offerings', function (Blueprint $table) {
            // Global default SIG (prescription directions) for this product.
            $table->text('sig')->nullable()->after('directions');

            // Structured pharmacy FK — nullable; pharmacy_name kept for backwards compat.
            $table->unsignedBigInteger('pharmacy_id')->nullable()->after('sig');
            $table->foreign('pharmacy_id')->references('id')->on('pharmacies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offerings', function (Blueprint $table) {
            $table->dropForeign(['pharmacy_id']);
            $table->dropColumn(['sig', 'pharmacy_id']);
        });
    }
};
