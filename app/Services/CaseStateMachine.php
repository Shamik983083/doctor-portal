<?php

namespace App\Services;

use App\Models\PatientCase;
use App\Models\Clinician;
use App\Models\CaseEvent;
use App\Services\WebhookDispatcher;
use App\Services\CaseAutoAssigner;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CaseStateMachine
{
    public function __construct(
        private WebhookDispatcher $webhookDispatcher,
        private CaseAutoAssigner $autoAssigner,
        private TriageClassifier $triageClassifier,
    ) {}

    public function transition(PatientCase $case, string $toStatus, array $context = []): PatientCase
    {
        if (!$case->canTransitionTo($toStatus)) {
            throw new InvalidArgumentException(
                "Cannot transition case from [{$case->status}] to [{$toStatus}]."
            );
        }

        // Capture before update() syncs the model's originals
        $fromStatus = $case->status;

        DB::transaction(function () use ($case, $toStatus, $context, $fromStatus) {
            $timestamps = [
                PatientCase::STATUS_ASSIGNED   => 'assigned_at',
                PatientCase::STATUS_APPROVED   => 'approved_at',
                PatientCase::STATUS_PROCESSING => 'processing_at',
                PatientCase::STATUS_COMPLETED  => 'completed_at',
                PatientCase::STATUS_CANCELLED  => 'cancelled_at',
            ];

            $updates = ['status' => $toStatus];

            if (isset($timestamps[$toStatus])) {
                $updates[$timestamps[$toStatus]] = now();
            }
            if ($toStatus === PatientCase::STATUS_CANCELLED && isset($context['reason'])) {
                $updates['cancellation_reason'] = $context['reason'];
            }
            if ($toStatus === PatientCase::STATUS_SUPPORT) {
                if (isset($context['support_note'])) {
                    $updates['support_note'] = $context['support_note'];
                }
                // Record first time this case entered support — never overwrite
                if (!$case->support_at) {
                    $updates['support_at'] = now();
                }
            }
            if ($toStatus === PatientCase::STATUS_ASSIGNED && isset($context['clinician_id'])) {
                $updates['clinician_id'] = $context['clinician_id'];
            }

            $case->update($updates);

            CaseEvent::create([
                'case_id'    => $case->id,
                'event_type' => 'status_changed',
                'actor_type' => $context['actor_type'] ?? 'system',
                'actor_id'   => $context['actor_id'] ?? null,
                'payload'    => ['from' => $fromStatus, 'to' => $toStatus],
                'notes'      => $context['notes'] ?? null,
            ]);
        });

        $case->refresh();
        $this->dispatchWebhookEvent($case, $toStatus);

        // Classify triage band as the case enters the review queue — runs before
        // auto-assign so the band is set whether or not a clinician is immediately available.
        if ($toStatus === PatientCase::STATUS_WAITING) {
            $case->loadMissing(['patient', 'caseOfferings.offering', 'caseQuestions']);
            $this->triageClassifier->apply($case);
        }

        // Auto-assign when entering the waiting queue, unless the caller is about to manually assign
        if ($toStatus === PatientCase::STATUS_WAITING && empty($context['skip_auto_assign'])) {
            $clinician = $this->autoAssigner->findNext($case);
            if ($clinician) {
                $this->assignToClinician($case, $clinician);
            }
        }

        return $case;
    }

    /**
     * Reassign an already-assigned case to a different clinician without a status change.
     * Used by admin to override both manual and auto-assignments.
     */
    public function reassign(PatientCase $case, Clinician $clinician): PatientCase
    {
        DB::transaction(function () use ($case, $clinician) {
            $case->update(['clinician_id' => $clinician->id]);

            CaseEvent::create([
                'case_id'    => $case->id,
                'event_type' => 'clinician_reassigned',
                'actor_type' => 'admin',
                'actor_id'   => null,
                'payload'    => ['clinician_id' => $clinician->id],
                'notes'      => "Reassigned to {$clinician->full_name}",
            ]);
        });

        return $case->refresh();
    }

    public function release(PatientCase $case): PatientCase
    {
        if (!$case->hold_status) {
            return $case;
        }
        $case->update(['hold_status' => false]);
        return $this->transition($case, PatientCase::STATUS_WAITING);
    }

    public function assignToClinician(PatientCase $case, Clinician $clinician): PatientCase
    {
        return $this->transition($case, PatientCase::STATUS_ASSIGNED, [
            'clinician_id' => $clinician->id,
            'actor_type'   => 'system',
        ]);
    }

    public function approve(PatientCase $case, int $clinicianId): PatientCase
    {
        return $this->transition($case, PatientCase::STATUS_APPROVED, [
            'actor_type' => 'clinician',
            'actor_id'   => $clinicianId,
        ]);
    }

    public function cancel(PatientCase $case, string $reason = '', int $actorId = null, string $actorType = 'system'): PatientCase
    {
        return $this->transition($case, PatientCase::STATUS_CANCELLED, [
            'reason'     => $reason,
            'actor_type' => $actorType,
            'actor_id'   => $actorId,
        ]);
    }

    public function returnToClinicianFromSupport(PatientCase $case, string $partnerNote): PatientCase
    {
        return $this->transition($case, PatientCase::STATUS_ASSIGNED, [
            'actor_type' => 'partner',
            'notes'      => $partnerNote,
        ]);
    }

    public function startProcessing(PatientCase $case): PatientCase
    {
        return $this->transition($case, PatientCase::STATUS_PROCESSING, ['actor_type' => 'clinician']);
    }

    public function complete(PatientCase $case): PatientCase
    {
        return $this->transition($case, PatientCase::STATUS_COMPLETED, ['actor_type' => 'system']);
    }

    public function escalateToSupport(PatientCase $case, string $note = ''): PatientCase
    {
        return $this->transition($case, PatientCase::STATUS_SUPPORT, [
            'support_note' => $note,
            'actor_type'   => 'system',
        ]);
    }

    private function dispatchWebhookEvent(PatientCase $case, string $status): void
    {
        $eventMap = [
            PatientCase::STATUS_CREATED    => 'case_created',
            PatientCase::STATUS_WAITING    => 'case_waiting',
            PatientCase::STATUS_SUPPORT    => 'case_support',
            PatientCase::STATUS_ASSIGNED   => 'case_assigned_to_clinician',
            PatientCase::STATUS_APPROVED   => 'case_approved',
            PatientCase::STATUS_PROCESSING => 'case_processing',
            PatientCase::STATUS_COMPLETED  => 'case_completed',
            PatientCase::STATUS_CANCELLED  => 'case_cancelled',
        ];

        $eventType = $eventMap[$status] ?? "case_{$status}";

        $this->webhookDispatcher->dispatch($case->partner_id, $eventType, [
            'case_id'    => $case->uuid,
            'patient_id' => $case->patient->uuid ?? null,
            'status'     => $status,
            'visit_type' => $case->visit_type,
            'timestamp'  => now()->timestamp,
        ]);
    }
}
