<?php

namespace App\Services;

use App\Models\Clinician;
use App\Models\PatientCase;
use Illuminate\Support\Facades\Log;

class CaseAutoAssigner
{
    /**
     * Find the highest-priority available clinician who has capacity.
     * Prefers clinicians licensed in the case's patient_state when known.
     * Falls back to the full eligible pool if no state-matched clinician exists,
     * so cases are never permanently stuck in waiting.
     */
    public function findNext(PatientCase $case): ?Clinician
    {
        $eligible = Clinician::where('status', 'active')
            ->where('is_available', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->filter(function (Clinician $clinician) {
                $activeCaseCount = PatientCase::whereIn('status', [
                    PatientCase::STATUS_ASSIGNED,
                    PatientCase::STATUS_APPROVED,
                ])->where('clinician_id', $clinician->id)->count();

                return $activeCaseCount < $clinician->max_daily_cases;
            });

        $state = $case->patient_state ? strtoupper($case->patient_state) : null;

        if ($state) {
            $stateFiltered = $eligible->filter(fn(Clinician $c) => $c->isLicensedInState($state));

            if ($stateFiltered->isNotEmpty()) {
                return $stateFiltered->first();
            }

            Log::warning('CaseAutoAssigner: no clinician licensed in state, falling back to unfiltered pool.', [
                'case_uuid'     => $case->uuid,
                'patient_state' => $state,
            ]);
        }

        return $eligible->first();
    }
}
