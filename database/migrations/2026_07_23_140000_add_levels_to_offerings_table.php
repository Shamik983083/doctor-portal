<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offerings', function (Blueprint $table) {
            // Ordered dose levels for this compound, e.g.
            // [{"label":"LVL1 - 1MG (0.25mg/wk)","formula":"0.25mg/0.5mg/0.5mL (2mL)"}, …]
            // When present, the prescribe form populates M1/M2/M3 dropdowns from
            // this list instead of the generic CATALOG family lookup.
            $table->json('levels')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('offerings', function (Blueprint $table) {
            $table->dropColumn('levels');
        });
    }
};
