<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture the product plan context at case-creation time so it survives
 * future edits to partner_product_plans.
 *
 * month_frequency — the billing duration the partner requested (1, 3, 6 …).
 *   Drives the duration pre-fill on the clinician's prescribe form and is
 *   echoed back in the prescription_written webhook.
 *
 * product_key — the partner's product identifier, snapshotted here so the
 *   webhook can echo it back even if the admin later edits the plan table.
 *   Null when the legacy offering_id path was used without a product_key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_offerings', function (Blueprint $table) {
            $table->unsignedTinyInteger('month_frequency')->nullable()->after('quantity');
            $table->string('product_key')->nullable()->after('month_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('case_offerings', function (Blueprint $table) {
            $table->dropColumn(['month_frequency', 'product_key']);
        });
    }
};
