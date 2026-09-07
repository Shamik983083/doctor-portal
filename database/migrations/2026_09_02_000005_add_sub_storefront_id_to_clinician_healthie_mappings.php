<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinician_healthie_mappings', function (Blueprint $table) {
            // Nullable FK: null = partner-level mapping, non-null = sub-storefront mapping.
            $table->foreignId('sub_storefront_id')
                ->nullable()
                ->after('partner_id')
                ->constrained('sub_storefronts')
                ->nullOnDelete();

            // Protect sub-storefront rows: in MySQL a unique index on a nullable
            // column treats NULL as distinct from non-NULL, so this constraint
            // only enforces uniqueness where sub_storefront_id IS NOT NULL.
            // Partner-level rows (sub_storefront_id = NULL) are still protected
            // by the pre-existing UNIQUE(clinician_id, partner_id) index.
            $table->unique(['clinician_id', 'sub_storefront_id'], 'chm_clinician_sub_storefront_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clinician_healthie_mappings', function (Blueprint $table) {
            $table->dropUnique('chm_clinician_sub_storefront_unique');
            $table->dropConstrainedForeignId('sub_storefront_id');
        });
    }
};
