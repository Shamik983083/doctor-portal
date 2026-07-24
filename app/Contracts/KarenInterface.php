<?php

namespace App\Contracts;

use App\Models\PatientCase;

/**
 * F20: Karen is the planned automated-outreach service.
 *
 * Binding the feature behind this interface lets the app boot with a no-op
 * implementation until the real service is built, and lets any future real
 * implementation swap in through the service container without touching call
 * sites.  All case events emitted by a Karen implementation must set
 * `actor_service = 'karen'` on the CaseEvent row so audit logs attribute the
 * action to the service rather than to a user or a bare 'system' string.
 */
interface KarenInterface
{
    /**
     * Notify the patient that their case is awaiting information.
     *
     * Implementations may send an email, an SMS, or enqueue a job — the
     * contract does not prescribe the channel.  A Karen event row must be
     * written to case_events with actor_service = 'karen'.
     */
    public function nudgePatient(PatientCase $case): void;

    /**
     * Notify the assigned clinician that their SLA deadline is approaching.
     */
    public function alertClinicianSla(PatientCase $case): void;

    /**
     * Return true when the real Karen service is wired up and active.
     */
    public function isEnabled(): bool;
}
