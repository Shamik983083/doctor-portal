<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mark a case as a re-bill / check-in rather than a first visit.
 *
 * TERMINOLOGY, BECAUSE THE WORD IS OVERLOADED HERE (Devin msg 2246).
 * This is NOT a pharmacy refill on an existing script. The `refills` integer on
 * offerings, prescriptions and case_offerings means that: how many times a
 * dispensed script may be filled again, and it is untouched by this.
 *
 * THIS flag means the patient submitted a CHECK-IN and a doctor prescribes
 * again. A recurring visit on an existing course of treatment. Devin's words:
 * "the client submits a check in and the doctor prescribes again. We want the
 * same doctors handling them if possible and to be able to project on our
 * reporting."
 *
 * So it earns a column rather than living in `visit_type`, for three reasons:
 *  1. ROUTING reads it to send a check-in back to the doctor who treated the
 *     patient before (see RoutingPolicyResolver + ContinuityResolver).
 *  2. REPORTING needs a first-visit vs check-in split that is countable in SQL.
 *     A free-text `visit_type` cannot be grouped on reliably.
 *  3. A CAP ON NEW CASES is coming and needs to distinguish the two (Devin msg
 *     2246: "We need a cap on new cases and a way to separate and track them").
 *     That cap is NOT built here; this is the column it will need.
 *
 * DEFAULTS FALSE, so every existing case reads as a first visit. That is the
 * safe direction: a check-in wrongly treated as a first visit just routes
 * normally, while a first visit wrongly treated as a check-in would hand a new
 * patient to a doctor on the strength of a history they do not have.
 *
 * ADDITIVE ONLY. Runs on push to staging via `artisan migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->boolean('is_refill')->default(false)->after('visit_type');

            // Reporting groups on this alongside status and dates, and routing
            // reads it per case. Cheap index, and the queries that need it are
            // the ones people run repeatedly on a dashboard.
            $table->index(['is_refill', 'status'], 'cases_is_refill_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex('cases_is_refill_status_index');
            $table->dropColumn('is_refill');
        });
    }
};
