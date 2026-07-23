<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rich dosing for a prescribed medication (Devin msg 2279: the review needs
 * dropdowns, multiple months and multiple medications).
 *
 * The existing columns store one medication's flat fields (name, refills,
 * quantity, ...). The full review model from MA-DOCPORTAL / the design preview
 * also carries, per medication, an administration frequency, a duration term,
 * and a dose PER MONTH of that term (the titration ladder). Those do not fit the
 * flat columns, so they live together in one `dosing` json blob:
 *
 *   { "medication": "Semaglutide", "frequency": "Weekly", "term": "3M",
 *     "months": ["L1 · 2.5 mg", "L2 · 5 mg", "L3 · 7.5 mg"] }
 *
 * NULLABLE, so every existing prescription medication and any submitted without
 * the ladder reads as "no structured dosing", and the flat columns still stand
 * on their own. The prescribe() endpoint keeps writing the flat columns exactly
 * as before; this only adds the structured detail alongside.
 *
 * ADDITIVE ONLY. Runs on push to staging via `artisan migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_prescription_medications', function (Blueprint $table) {
            $table->json('dosing')->nullable()->after('compound_formula');
        });
    }

    public function down(): void
    {
        Schema::table('case_prescription_medications', function (Blueprint $table) {
            $table->dropColumn('dosing');
        });
    }
};
