<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags cases that were created automatically by the GLP auto-renewal
 * scheduler rather than by a patient or partner. The clinician sees a
 * banner on the prescribe form so they know the dose was system-suggested.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->boolean('is_auto_renewal')->default(false)->after('is_refill');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('is_auto_renewal');
        });
    }
};
