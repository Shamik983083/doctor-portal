<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('webhooks:recover')->everyFiveMinutes();

/*
 * Routing exceptions (Devin msg 2308: "NO SILENT FAILURES"). The recorder only
 * escalates when something tries to route a case, so a case that failed once and
 * was never retried would age unnoticed. This sweep is what makes the escalation
 * a guarantee rather than a side effect of traffic.
 */
Schedule::command('routing:sweep-exceptions')->everyFifteenMinutes();

// B3: warn clinicians whose pool-assigned cases are within 15 minutes of their
// completion deadline, and auto-release any that have already passed it.
Schedule::command('case:check-deadlines')->everyFiveMinutes();

// EHR outbox retry: re-push failed records until they succeed or exhaust the
// attempt ceiling (ehr.max_attempts). withoutOverlapping() prevents a slow
// Healthie response from stacking a second instance on top of a still-running
// sweep. Only runs when EHR_ENABLED=true — the command guards internally too.
Schedule::command('ehr:retry')->everyFifteenMinutes()->withoutOverlapping();

// GLP dose titration auto-renewal: finds completed monthly GLP injectable cases
// where completed_at was 28–32 days ago, creates a renewal case with the next
// dose level pre-prescribed, and fires a case_auto_renewed webhook.
Schedule::command('glp:auto-renew')->dailyAt('08:30')->withoutOverlapping();
