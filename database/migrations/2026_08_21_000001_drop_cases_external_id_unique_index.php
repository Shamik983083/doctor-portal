<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check actual DB state before acting — staging may not have the
        // unique index if the DB was initialised without it, which caused
        // the original migration to fail and block every subsequent deploy.
        $uniqueExists = collect(DB::select(
            "SHOW INDEX FROM `cases` WHERE Key_name = 'cases_partner_id_external_id_unique'"
        ))->isNotEmpty();

        $regularExists = collect(DB::select(
            "SHOW INDEX FROM `cases` WHERE Key_name = 'cases_partner_id_external_id_index'"
        ))->isNotEmpty();

        Schema::table('cases', function (Blueprint $table) use ($uniqueExists, $regularExists) {
            if ($uniqueExists) {
                $table->dropUnique(['partner_id', 'external_id']);
            }
            if (! $regularExists) {
                $table->index(['partner_id', 'external_id']);
            }
        });
    }

    public function down(): void
    {
        $regularExists = collect(DB::select(
            "SHOW INDEX FROM `cases` WHERE Key_name = 'cases_partner_id_external_id_index'"
        ))->isNotEmpty();

        $uniqueExists = collect(DB::select(
            "SHOW INDEX FROM `cases` WHERE Key_name = 'cases_partner_id_external_id_unique'"
        ))->isNotEmpty();

        Schema::table('cases', function (Blueprint $table) use ($regularExists, $uniqueExists) {
            if ($regularExists) {
                $table->dropIndex(['partner_id', 'external_id']);
            }
            if (! $uniqueExists) {
                $table->unique(['partner_id', 'external_id']);
            }
        });
    }
};
