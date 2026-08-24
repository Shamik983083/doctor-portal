<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL error 1553: cannot drop a unique index that a foreign key
        // depends on unless a replacement index exists first.  We therefore
        // ADD the regular index before dropping the unique one so MySQL
        // always has an index covering (partner_id, external_id).

        $uniqueExists = collect(DB::select(
            "SHOW INDEX FROM `cases` WHERE Key_name = 'cases_partner_id_external_id_unique'"
        ))->isNotEmpty();

        $regularExists = collect(DB::select(
            "SHOW INDEX FROM `cases` WHERE Key_name = 'cases_partner_id_external_id_index'"
        ))->isNotEmpty();

        // Step 1 — add replacement index first (safe to run even if unique still exists)
        if (! $regularExists) {
            Schema::table('cases', function (Blueprint $table) {
                $table->index(['partner_id', 'external_id']);
            });
        }

        // Step 2 — now safe to drop the unique index; regular index covers the gap
        if ($uniqueExists) {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropUnique(['partner_id', 'external_id']);
            });
        }
    }

    public function down(): void
    {
        $regularExists = collect(DB::select(
            "SHOW INDEX FROM `cases` WHERE Key_name = 'cases_partner_id_external_id_index'"
        ))->isNotEmpty();

        $uniqueExists = collect(DB::select(
            "SHOW INDEX FROM `cases` WHERE Key_name = 'cases_partner_id_external_id_unique'"
        ))->isNotEmpty();

        // Step 1 — restore unique index first, then drop the regular one
        if (! $uniqueExists) {
            Schema::table('cases', function (Blueprint $table) {
                $table->unique(['partner_id', 'external_id']);
            });
        }

        if ($regularExists) {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropIndex(['partner_id', 'external_id']);
            });
        }
    }
};
