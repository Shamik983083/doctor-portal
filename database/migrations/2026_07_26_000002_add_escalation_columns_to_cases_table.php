<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // Unified escalation model (Phase 1c).
            // escalation_target differentiates the three escalation paths:
            //   doctor_admin   — provider escalates to their Doctor Admin (B10)
            //   support        — storefront escalation to the portal support team (existing behaviour)
            //   client_response — awaiting a reply from the client/patient before the case can progress (D15/D16)
            //
            // Null on non-escalated cases. The status column continues to drive
            // the state machine; this column adds routing context within that status.
            $table->string('escalation_target', 32)->nullable()->after('support_note');
            $table->text('escalation_reason')->nullable()->after('escalation_target');
        });

        // Seed: existing cases already in 'support' status map to the pre-existing behaviour.
        DB::table('cases')
            ->where('status', 'support')
            ->whereNull('escalation_target')
            ->update(['escalation_target' => 'support']);
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['escalation_target', 'escalation_reason']);
        });
    }
};
