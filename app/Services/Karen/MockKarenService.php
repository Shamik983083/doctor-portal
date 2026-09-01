<?php

namespace App\Services\Karen;

use App\Contracts\KarenInterface;
use App\Models\PatientCase;
use Illuminate\Support\Facades\Log;

/**
 * F20: No-op placeholder bound when KAREN_ENABLED=false (the default).
 *
 * Every method logs what it would have done so the audit trail in the
 * application logs shows Karen calls during development and staging, making
 * it easy to verify call sites without needing the real service.
 */
class MockKarenService implements KarenInterface
{
    public function nudgePatient(PatientCase $case): void
    {
        Log::info('[karen:mock] nudgePatient skipped — Karen is disabled', [
            'case_uuid' => $case->uuid,
        ]);
    }

    public function alertClinicianSla(PatientCase $case): void
    {
        Log::info('[karen:mock] alertClinicianSla skipped — Karen is disabled', [
            'case_uuid'    => $case->uuid,
            'clinician_id' => $case->clinician_id,
        ]);
    }

    public function isEnabled(): bool
    {
        return false;
    }
}
