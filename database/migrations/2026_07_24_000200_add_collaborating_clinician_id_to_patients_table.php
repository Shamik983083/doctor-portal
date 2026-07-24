<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('collaborating_clinician_id')
                  ->nullable()
                  ->after('partner_id')
                  ->constrained('clinicians')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Clinician::class, 'collaborating_clinician_id');
            $table->dropColumn('collaborating_clinician_id');
        });
    }
};
