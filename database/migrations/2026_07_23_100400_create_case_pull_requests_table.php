<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A doctor asking the pool for work (Devin msg 2308, PROVIDER_POOL).
 *
 * "A PROVIDER CAN REQUEST CASES (A NUMBER, THEY DON'T SEE WHAT'S AVAILABLE).
 * THEN IT CHECKS EVERYTHING UP TOP AND LOOKS AT THE PROVIDER TO MAKE SURE
 * THEY'RE ELIGIBLE TO BE ASSIGNED CASES ... THEN TRANSFER CASES TO THEM WITH THE
 * OLDEST IN THE SYSTEM FIRST."
 *
 * WHY THE REQUEST IS A ROW AND NOT JUST AN ACTION. Three reasons, all of which
 * showed up in the requirement rather than being invented here:
 *
 *  1. A request can be HELD. Under an SLA policy set to REQUIRE_APPROVAL the
 *     Doctor Admin decides, so the request has to survive between the asking and
 *     the answering.
 *  2. A refusal has to be EXPLAINABLE. "You asked for 20 and got 7" and "you
 *     asked for 20 and got nothing because you are over your overdue-case
 *     threshold" are different answers, and a doctor who cannot see the queue can
 *     only learn which one applies from what we record here.
 *  3. It is the audit trail for cases that moved without the router choosing.
 *
 * STATUS: PENDING_APPROVAL | GRANTED | DENIED | REJECTED
 *   PENDING_APPROVAL  an SLA breach with on_violation=REQUIRE_APPROVAL
 *   GRANTED           cases were transferred (granted_count may be less than
 *                     requested_count, and shortfall_reason says why)
 *   DENIED            a Doctor Admin refused a held request
 *   REJECTED          blocked outright by eligibility, never reached an admin
 *
 * `blocking_reasons` and `granted_case_ids` are JSON rather than joins: they are
 * a snapshot of what was true at decision time, and a join would silently rewrite
 * history as the underlying rows change.
 *
 * ADDITIVE ONLY. Runs on push to staging via `artisan migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinician_id')->constrained('clinicians')->cascadeOnDelete();

            $table->unsignedSmallInteger('requested_count');
            $table->unsignedSmallInteger('granted_count')->default(0);

            $table->string('status', 25)->default('GRANTED');

            $table->json('blocking_reasons')->nullable();
            $table->json('granted_case_ids')->nullable();
            $table->text('shortfall_reason')->nullable();

            // Set when an SLA breach sent this to a Doctor Admin.
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();

            $table->index(['clinician_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_pull_requests');
    }
};
