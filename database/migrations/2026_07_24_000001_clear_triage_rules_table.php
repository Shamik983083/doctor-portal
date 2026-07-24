<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Triage v2: disqualifier rules are now derived from questionnaire question
     * options (is_disqualify: true). The old BMI / age / keyword / offering rows
     * in triage_rules are no longer evaluated by the classifier, so we clear them.
     * The table itself is retained for potential future use.
     */
    public function up(): void
    {
        DB::table('triage_rules')->truncate();
    }

    public function down(): void
    {
        // No rollback — re-run the original triage_rules migration seeder if needed.
    }
};
