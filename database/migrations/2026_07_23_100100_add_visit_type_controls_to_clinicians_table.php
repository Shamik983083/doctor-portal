<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-doctor visit-type acceptance and the scheduling link (Devin msg 2313 Q4).
 *
 * "the Sync is determined by states. we'll need to be able to integrate a
 * calendly link for the provider if its a sync visit so they can book on there"
 *
 * THE TWO HALVES OF THE VISIT-TYPE AXIS.
 * The CASE half (does this visit have to be synchronous?) is decided by state,
 * and lives in `state_visit_requirements`. This migration is the PROVIDER half:
 * which types this doctor is open to, and where a patient books them if the
 * answer is synchronous.
 *
 * DEFAULTS REPRODUCE TODAY EXACTLY.
 *  - accepts_async_visits defaults TRUE: every case in the system today is
 *    asynchronous, so every doctor must keep taking them.
 *  - accepts_sync_visits defaults FALSE: nobody has a booking link yet, and a
 *    doctor with no link cannot actually receive a synchronous patient. Opting in
 *    is a deliberate act that comes with supplying the link.
 *
 * Since `state_visit_requirements` starts empty and no case resolves to
 * synchronous until a super admin writes a rule, the false default blocks nothing
 * on the day it deploys.
 *
 * WHY THE LINK IS PART OF THE GATE, NOT DECORATION.
 * Assigning a synchronous case to a doctor with no booking link produces a case
 * the patient cannot act on, which looks identical to a working assignment from
 * the routing side and is discovered by the patient. The evaluator therefore
 * treats a missing link on a synchronous case as a hard block, so the case waits
 * visibly in the exceptions queue instead.
 *
 * ADDITIVE ONLY. Runs on push to staging via `artisan migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinicians', function (Blueprint $table) {
            $table->boolean('accepts_async_visits')->default(true)->after('daily_refill_alert_threshold');
            $table->boolean('accepts_sync_visits')->default(false)->after('accepts_async_visits');

            // Calendly today, any booking URL tomorrow. Deliberately not named
            // calendly_url: the column outlives the vendor.
            $table->string('scheduling_link', 500)->nullable()->after('accepts_sync_visits');
        });
    }

    public function down(): void
    {
        Schema::table('clinicians', function (Blueprint $table) {
            $table->dropColumn(['accepts_async_visits', 'accepts_sync_visits', 'scheduling_link']);
        });
    }
};
