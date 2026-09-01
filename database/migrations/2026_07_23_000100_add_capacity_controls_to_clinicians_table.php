<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-doctor capacity controls (Devin msgs 2248, 2250).
 *
 * FOUR COLUMNS, AND ONE OF THEM CLOSES A LIVE BUG.
 *
 * Until now `max_daily_cases` alone drove TWO different eligibility caps in
 * RoutingPolicyResolver:
 *
 *     'maxDailyVolume' => $maxDaily,   // cases assigned since midnight, resets daily
 *     'maxOpenCases'   => $maxDaily,   // all non-terminal cases, never resets
 *
 * So a doctor capped at 20 who accumulated 20 OPEN cases over two weeks was
 * permanently blocked from new work having taken zero today. `max_open_cases`
 * splits the two so the open-case ceiling is its own, deliberately-set number.
 *
 * THE OTHER THREE ARE THE NEW CONTROLS.
 *
 *  - accepting_new_cases: the "my books are full" switch (Devin msg 2250: "some
 *    may only want to get no new cases because their books are full"). A HARD
 *    block on NEW cases only. A doctor with it off still receives their own
 *    returning patients (check-ins), which route by continuity and are never
 *    withheld (msg 2250 A).
 *
 *  - max_daily_new_cases: a daily ceiling on NEW cases specifically, separate
 *    from the overall max_daily_cases. Also new-only.
 *
 *  - daily_refill_alert_threshold: SOFT. Refills are never withheld (msg 2250 A:
 *    "Let it run and alert the doctor admin but don't withhold"). When a doctor
 *    passes this many check-ins in a day, the admins over them are alerted; the
 *    case still routes to that doctor. Null means no alert.
 *
 * NULL EVERYWHERE MEANS "NO LIMIT", matching how max_daily_cases already reads a
 * null/0 as uncapped. accepting_new_cases defaults TRUE, so every existing
 * doctor keeps taking new cases until someone turns them off. Nothing changes on
 * the day this runs.
 *
 * ADDITIVE ONLY. Runs on push to staging via `artisan migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinicians', function (Blueprint $table) {
            $table->boolean('accepting_new_cases')->default(true)->after('max_daily_cases');
            $table->unsignedSmallInteger('max_daily_new_cases')->nullable()->after('accepting_new_cases');
            $table->unsignedSmallInteger('max_open_cases')->nullable()->after('max_daily_new_cases');
            $table->unsignedSmallInteger('daily_refill_alert_threshold')->nullable()->after('max_open_cases');
        });
    }

    public function down(): void
    {
        Schema::table('clinicians', function (Blueprint $table) {
            $table->dropColumn([
                'accepting_new_cases',
                'max_daily_new_cases',
                'max_open_cases',
                'daily_refill_alert_threshold',
            ]);
        });
    }
};
