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
