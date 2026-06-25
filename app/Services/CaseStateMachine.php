<?php

namespace App\Services;

use App\Models\PatientCase;
use App\Models\Clinician;
use App\Models\CaseEvent;
use App\Services\WebhookDispatcher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CaseStateMachine
{
    public function __construct(private WebhookDispatcher $webhookDispatcher) {}

    public function transition(PatientCase $case, string $toStatus, array $context = []): PatientCase
    {
        if (!$case->canTransitionTo($toStatus)) {
            throw new InvalidArgumentException(
                "Cannot transition case from [{$case->status}] to [{$toStatus}]."
            );
        }

        DB::transaction(function () use ($case, $toStatus, $context) {
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
                'payload'    => ['from' => $case->getOriginal('status'), 'to' => $toStatus],
                'notes'      => $context['notes'] ?? null,
            ]);
        });

        $case->refresh();
        $this->dispatchWebhookEvent($case, $toStatus);

        return $case;
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

    public function startProcessing(PatientCase $case): PatientCase
    {
        return $this->transition($case, PatientCase::STATUS_PROCESSING, ['actor_type' => 'partner']);
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
            'timestamp'  => now()->timestamp,
        ]);
    }
}
