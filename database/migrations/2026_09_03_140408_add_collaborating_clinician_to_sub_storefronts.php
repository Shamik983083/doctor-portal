<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_storefronts', function (Blueprint $table) {
            // The default collaborating clinician copied onto new patients arriving
            // from this sub-storefront. Overrides partner.collaborating_clinician_id
            // when set. Must always be one of the sub-storefront's assigned clinicians.
            $table->foreignId('collaborating_clinician_id')
                ->nullable()
                ->after('status')
                ->constrained('clinicians')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sub_storefronts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collaborating_clinician_id');
        });
    }
};
