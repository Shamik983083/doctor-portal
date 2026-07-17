<?php

use App\Models\PatientCase;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Case messaging channel — clinician assigned to the case, or any admin
Broadcast::channel('case.{caseId}', function ($user, $caseId) {
    $case = PatientCase::find($caseId);
    if (! $case) {
        return false;
    }
    if ($user->clinician && $case->clinician_id === $user->clinician->id) {
        return true;
    }
    return $user->hasRole('admin');
});

// Provider inbox — any authenticated clinician or admin may subscribe
Broadcast::channel('provider-inbox', function ($user) {
    return $user->hasAnyRole(['clinician', 'admin', 'super_admin']);
});
