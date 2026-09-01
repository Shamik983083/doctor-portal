<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner product plans — one-to-many product ↔ offering mapping.
 *
 * A partner's "product" (identified by product_key, their own identifier)
 * can map to different offerings depending on the billing duration
 * (month_frequency). For example:
 *
 *   product_key="glp1-weightloss", month_frequency=1  → offering_id=X (1-month SKU)
 *   product_key="glp1-weightloss", month_frequency=3  → offering_id=Y (3-month SKU)
 *   product_key="glp1-weightloss", month_frequency=6  → offering_id=Z (6-month SKU)
 *
 * Partners may submit (product_key + month_frequency) in the case creation API
 * instead of offering_id directly. The portal resolves to the correct offering
 * internally. The legacy offering_id path is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_product_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('product_key');                    // partner's own product identifier
            $table->foreignId('offering_id')->constrained('offerings')->cascadeOnDelete();
            $table->unsignedTinyInteger('month_frequency');   // 1, 3, 6, 12, etc.
            $table->string('label')->nullable();              // optional admin label, e.g. "3-Month GLP-1"
            $table->timestamps();

            // One row per partner × product × duration variant (explicit short name — MySQL 64-char limit)
            $table->unique(['partner_id', 'product_key', 'month_frequency'], 'ppp_partner_key_freq_unique');
            $table->index(['partner_id', 'product_key'], 'ppp_partner_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_product_plans');
    }
};
