<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Routing policy version 2: separate modes for new cases and check-ins
 * (Devin msg 2308).
 *
 * "THERE ARE 2 CHECKS: 1. NEW CLIENTS 2. REFILL CLIENTS. WE NEED TO HAVE CAPS AND
 * ROUTING FOR EACH."
 *
 * WHAT CHANGES IN THE POLICY SHAPE. A version used to carry one `mode`. It now
 * carries `newMode` and `refillMode` in config, and the `mode` column stays as
 * the fallback for both, so every existing version (v1 PRIORITY) keeps meaning
 * exactly what it meant. See RoutingPolicy::modeForCase().
 *
 * SEEDED AS A DRAFT, NOT ACTIVE. v1 stays in force. Activating v2 is a deliberate
 * act on the routing screen, which is the whole reason policies are versioned.
 * Its values below are simply a starting point to edit.
 *
 * NOTE ON requireRecordedLicensure. It appears here as true, but it is no longer
 * what decides the question: per Devin msg 2313 ("empty should not show licensed
 * everywhere it needs to reject") blank licensure now fails closed in the code
 * default, so it rejects under v1 as well. The flag is kept so an operator can
 * see the position stated on the version, not to leave a door open.
 *
 * ADDITIVE ONLY, and it inserts a DRAFT, so it changes no routing outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: a re-run must not collide with the unique version index.
        if (DB::table('routing_policies')->where('version', 2)->exists()) {
            return;
        }

        $latest = (int) DB::table('routing_policies')->max('version');

        DB::table('routing_policies')->insert([
            'version' => max(2, $latest + 1),
            'mode'    => 'PRIORITY',           // fallback for any path not set below
            'config'  => json_encode([
                // The two paths (Devin msg 2308).
                'newMode'    => 'PRIORITY',
                'refillMode' => 'PRIORITY',

                'providerWeights'            => (object) [],
                'messageAgingThresholdHours' => null,

                // Blank licensure blocks. Stated on the version for the operator;
                // the code default now enforces it regardless.
                'requireRecordedLicensure'   => true,

                /*
                 * POOL ELIGIBILITY (Devin msg 2313 Q5: "adjust it for pool
                 * eligibility. I think there was confusion on the initial build
                 * but that was meant to be the logic behind the pool").
                 *
                 * These are the coefficients that used to drive INTELLIGENT mode.
                 * They now decide whether a doctor may PULL from the pool, which
                 * is what they were meant for. Null means the criterion is off.
                 */
                'poolCriteria' => [
                    'maxOutstandingCases'   => null,
                    'maxOverdueCases'       => null,
                    'overdueAfterHours'     => 48,
                    'maxAwaitingReply'      => null,
                    'maxCasesPerRequest'    => 25,
                    'maxCasesPerDay'        => null,
                ],
            ]),
            'status'       => 'DRAFT',
            'activated_at' => null,
            'created_by'   => null,
            'activated_by' => null,
            'note'         => 'Dual-path routing: separate modes and caps for new cases and check-ins, '
                . 'plus pool eligibility criteria. Draft. Activating this changes which doctor new cases go to.',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('routing_policies')
            ->where('version', 2)
            ->where('status', 'DRAFT')
            ->delete();
    }
};
