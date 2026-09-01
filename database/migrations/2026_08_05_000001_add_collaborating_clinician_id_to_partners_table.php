<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            // E19: optional default collaborating clinician for this storefront.
            // When a case arrives from this partner and the patient has no
            // collaborating clinician yet, this value is copied onto the patient.
            // onDelete set null so removing a clinician does not orphan partners.
            $table->foreignId('collaborating_clinician_id')
                  ->nullable()
                  ->after('settings')
                  ->constrained('clinicians')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropForeign(['collaborating_clinician_id']);
            $table->dropColumn('collaborating_clinician_id');
        });
    }
};
