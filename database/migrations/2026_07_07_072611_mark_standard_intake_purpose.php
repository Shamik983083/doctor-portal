<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Standard Intake questionnaires are shared sub-forms embedded inside
        // other questionnaires. They should not be directly attachable to
        // offerings, so we give them a dedicated purpose value.
        DB::table('questionnaires')
            ->where('name', 'Standard Intake 1')
            ->update(['purpose' => 'standard_intake']);
    }

    public function down(): void
    {
        DB::table('questionnaires')
            ->where('name', 'Standard Intake 1')
            ->where('purpose', 'standard_intake')
            ->update(['purpose' => 'clinical']);
    }
};
