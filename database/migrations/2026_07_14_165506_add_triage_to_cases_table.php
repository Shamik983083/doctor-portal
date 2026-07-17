<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice B — clinical triage classification (Green / Yellow / Red).
 *
 * Adds a lightweight triage axis to every case. Triage is SEPARATE from
 * workflow `status` and from `hold_status`: it answers "how much scrutiny
 * does this case need?" not "where is it in the pipeline?".
 *
 * Columns are nullable so existing rows migrate cleanly; the
 * `cases:triage-backfill` command (or the state-machine hook on entry to
 * the waiting queue) populates them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // green | yellow | red  (null = not yet classified)
            $table->string('triage', 12)->nullable()->after('hold_status');
            // machine-readable reason codes captured at classification time
            $table->json('triage_reasons')->nullable()->after('triage');
            // which ruleset version produced the classification (auditability)
            $table->string('triage_ruleset', 32)->nullable()->after('triage_reasons');
            $table->timestamp('triaged_at')->nullable()->after('triage_ruleset');

            $table->index(['status', 'triage']);
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['status', 'triage']);
            $table->dropColumn(['triage', 'triage_reasons', 'triage_ruleset', 'triaged_at']);
        });
    }
};
