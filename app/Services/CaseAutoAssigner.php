<?php

namespace App\Services;

use App\Models\Clinician;
use App\Models\PatientCase;

class CaseAutoAssigner
{
    /**
     * Find the highest-priority available clinician who has capacity.
     * Returns null when no clinician is eligible (auto-assign skipped).
     */
    public function findNext(PatientCase $case): ?Clinician
    {
        return Clinician::where('status', 'active')
            ->where('is_available', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->first(function (Clinician $clinician) {
                $activeCaseCount = PatientCase::whereIn('status', [
                    PatientCase::STATUS_ASSIGNED,
                    PatientCase::STATUS_APPROVED,
                ])->where('clinician_id', $clinician->id)->count();

                return $activeCaseCount < $clinician->max_daily_cases;
            });
    }
}
