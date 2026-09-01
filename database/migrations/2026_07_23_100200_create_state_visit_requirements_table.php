<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The state synchronous-visit matrix (Devin msg 2313 Q4).
 *
 * "yes MA's is [the source of truth] and we need to adjust as super admin as laws
 * change frequently. the Sync is determined by states."
 *
 * WHAT THIS DECIDES. Whether a given case must be a SYNCHRONOUS (live video)
 * visit, or may be handled asynchronously. It is a property of the patient's
 * state and the thing being prescribed, never of the doctor. The doctor side of
 * the axis is `clinicians.accepts_sync_visits`.
 *
 * PORTED FROM MA-DOCPORTAL's state visit-requirement policy, which is the source
 * of truth for this axis per Devin's answer. MA's shape carried over: a scope
 * discriminator, per-state rows, effective dating, an author, and a note.
 *
 * SCOPE, MOST SPECIFIC WINS. A rule applies to everything, to one category, or to
 * one offering:
 *
 *     OFFERING  beats  CATEGORY  beats  ALL
 *
 * so a blanket "TN requires video" can be narrowed by a single offering without
 * rewriting the blanket rule, which is how these laws actually arrive.
 *
 * WHY EFFECTIVE DATES RATHER THAN EDITING IN PLACE. Telehealth law changes on a
 * date, and cases routed last month were routed under last month's rule. An
 * edited row cannot answer "what was required when this case was seen"; a dated
 * one can. `effective_to` null means still in force.
 *
 * WHAT HAPPENS ON DAY ONE. The table ships EMPTY, so no rule matches, so every
 * case resolves ASYNCHRONOUS exactly as today. The existing per-offering
 * `offerings.video_required_states` list is still honoured as a fallback by
 * StateVisitRequirementResolver, so the video flags already configured on
 * offerings keep working and nothing has to be re-entered.
 *
 * ADDITIVE ONLY. Runs on push to staging via `artisan migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_visit_requirements', function (Blueprint $table) {
            $table->id();

            // ALL | CATEGORY | OFFERING. Kept as a string rather than an enum so a
            // new scope can be added without an ALTER on a compliance table.
            $table->string('scope_type', 20)->default('ALL');
            $table->foreignId('offering_category_id')->nullable()->constrained('offering_categories')->nullOnDelete();
            $table->foreignId('offering_id')->nullable()->constrained('offerings')->nullOnDelete();

            $table->string('state', 2);
            $table->boolean('requires_synchronous')->default(true);

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['state', 'scope_type']);
            $table->index(['effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_visit_requirements');
    }
};
