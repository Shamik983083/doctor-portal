<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the (partner_id, product_key, month_frequency) unique constraint so that
 * one product_key + month_frequency combination can map to multiple offerings
 * (Plan B — one-to-many fan-out). Duplicate prevention is now enforced at the
 * controller level: (partner_id, product_key, month_frequency, offering_id) must
 * be unique, stopping the exact same row from being inserted twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_product_plans', function (Blueprint $table) {
            $table->dropUnique('ppp_partner_key_freq_unique');
        });
    }

    public function down(): void
    {
        Schema::table('partner_product_plans', function (Blueprint $table) {
            $table->unique(['partner_id', 'product_key', 'month_frequency'], 'ppp_partner_key_freq_unique');
        });
    }
};
