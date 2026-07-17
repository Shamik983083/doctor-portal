<?php

use App\Models\PatientCase;
use Illuminate\Support\Facades\Broadcast;

// Case messaging channel — authorize if the user is the assigned clinician or admin
Broadcast::channel('case.{caseId}', function ($user, $caseId) {
    $case = PatientCase::find($caseId);
    if (! $case) {
        return false;
    }

    // Clinician assigned to this case
    if ($user->clinician && $case->clinician_id === $user->clinician->id) {
        return true;
    }

    // Admin users can access all cases
    if ($user->hasRole('admin')) {
        return true;
    }

    return false;
});
