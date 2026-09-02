<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinicians', function (Blueprint $table) {
            // A global clinician is provisioned into every Healthie-enabled sub-org.
            // Defaults true so all existing clinicians keep their current behaviour
            // (they were already working across all storefronts with no scoping).
            $table->boolean('is_global')->default(true)->after('pool_cooldown_until');
        });
    }

    public function down(): void
    {
        Schema::table('clinicians', function (Blueprint $table) {
            $table->dropColumn('is_global');
        });
    }
};
