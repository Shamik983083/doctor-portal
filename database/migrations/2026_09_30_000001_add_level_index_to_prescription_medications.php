<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which index in offering.levels was prescribed.
 * Stored at prescription time so titration logic never needs to
 * re-derive the position by string-matching the formula later.
 * -1 = clinician chose a custom formula not from the levels ladder.
 * NULL = legacy prescription created before this column existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_prescription_medications', function (Blueprint $table) {
            $table->tinyInteger('level_index')->nullable()->after('dosing');
        });
    }

    public function down(): void
    {
        Schema::table('case_prescription_medications', function (Blueprint $table) {
            $table->dropColumn('level_index');
        });
    }
};
