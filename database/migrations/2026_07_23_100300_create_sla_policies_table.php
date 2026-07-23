<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SLA policy, set by a Doctor Admin and pushed down to their doctors
 * (Devin msg 2313 Q6).
 *
 * "SLA is going to be adjusted by doctor admin and pushed down so we need a node
 * for that. it should bypass, or seek approval from Dr Admin"
 *
 * WHOSE POLICY THIS IS. `owner_user_id` is the Doctor Admin who set it. It
 * applies to the doctors that admin is over, through the existing
 * `admin_clinician` pivot. A doctor under two admins takes the STRICTER answer,
 * resolved in SlaPolicy::forClinician(), because an SLA is a floor and the looser
 * of two floors is not a floor.
 *
 * A super admin may own a policy with no doctors attached, which acts as the
 * house default for any doctor whose admin has not set one.
 *
 * THE THREE MEASURES, all optional, all null-means-off:
 *   - max_outstanding_cases      open non-terminal cases the doctor is carrying
 *   - max_overdue_cases          cases sitting longer than overdue_after_hours
 *   - max_median_decision_minutes   how long they take to decide, rolling
 *
 * WHAT HAPPENS ON A VIOLATION, and this is the part Devin specified directly.
 * `on_violation` is either:
 *
 *   BYPASS            let the pull through, and alert the Doctor Admin. The
 *                     doctor is not stopped, the admin is told.
 *   REQUIRE_APPROVAL  hold the pull request as PENDING and ask the Doctor Admin
 *                     to approve or deny it. Nothing is granted until they do.
 *
 * REQUIRE_APPROVAL is the default. A pull is a doctor taking on more work while
 * already behind an SLA their admin set, so the admin gets the say unless they
 * deliberately choose otherwise.
 *
 * THIS ONLY EVER GATES A PULL. An SLA breach never blocks a push assignment and
 * never blocks a check-in reaching its own doctor: those are the patient's route
 * to care, not the doctor's request for more of it.
 *
 * ADDITIVE ONLY. Runs on push to staging via `artisan migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 150)->default('SLA policy');

            $table->unsignedSmallInteger('max_outstanding_cases')->nullable();
            $table->unsignedSmallInteger('max_overdue_cases')->nullable();
            $table->unsignedSmallInteger('overdue_after_hours')->nullable();
            $table->unsignedInteger('max_median_decision_minutes')->nullable();

            // BYPASS | REQUIRE_APPROVAL
            $table->string('on_violation', 20)->default('REQUIRE_APPROVAL');

            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
