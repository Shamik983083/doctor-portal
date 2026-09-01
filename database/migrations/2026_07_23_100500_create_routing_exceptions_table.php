<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cases that failed to route (Devin msg 2308).
 *
 * "ANY FAILURE NEEDS TO BE LOUD, WE NEED THE DOCTOR ADMIN AND SUPER ADMIN TO SEE
 * CASES THAT AREN'T ASSIGNED OR HAVE AN ISSUE OR AN ERROR NO SILENT FAILURES"
 *
 * THE POINT OF THIS TABLE. Before it, a case that could not be routed produced a
 * log line and an empty `clinician_id`, which is indistinguishable from a case
 * that simply has not been routed yet. The absence of an assignment was the only
 * evidence, and absence is exactly what nobody notices. This turns that absence
 * into a row somebody owns.
 *
 * ONE OPEN ROW PER CASE. Re-routing the same case does not stack rows: the
 * existing open row is updated with the newest reason and its `occurrences`
 * count goes up. A case retried by a queue worker every few minutes would
 * otherwise bury every other exception.
 *
 * `first_seen_at` IS THE ONE THAT MATTERS. Notification escalates on the age of
 * the exception, not on the age of the row, so a case that has been unroutable
 * for six hours keeps reading as six hours old however many times it retried.
 *
 * REASON CODES are the EligibilityEvaluator codes plus the routing-level ones
 * (NO_ELIGIBLE_PROVIDER, NO_ACTIVE_POLICY, UNKNOWN_MODE). Never a generic
 * "unassigned": the whole value is in saying which gate closed.
 *
 * ADDITIVE ONLY. Runs on push to staging via `artisan migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();

            $table->string('reason_code', 60);
            $table->text('detail')->nullable();

            // Per-provider block reasons at the moment routing gave up, so the
            // screen can say "all four doctors blocked, three on licence".
            $table->json('provider_reasons')->nullable();

            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();

            // Set when the case finally routes, or when an admin clears it.
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            // Stops the escalation notification firing twice for one exception.
            $table->timestamp('notified_at')->nullable();

            $table->timestamps();

            $table->index(['resolved_at', 'first_seen_at']);
            $table->index('case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_exceptions');
    }
};
