<?php

namespace App\Jobs;

use App\Events\CaseMessageSent;
use App\Models\Message;
use App\Models\PatientCase;
use App\Services\AiAssistService;
use App\Services\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * A1: draft and send an intake confirmation message to the patient when their
 * case is assigned to a clinician.
 *
 * The message is queued so it does not hold up the state-machine transition.
 * When AI is available, the draft is personalised from the case record.
 * When it is not (KAREN disabled, no instruction set, provider unreachable),
 * a deterministic template fires instead so the patient always hears something.
 *
 * The message is created as an outbound portal message (direction='outbound',
 * sender_type='system') — the same channel the clinician uses to write to the
 * patient, so the patient sees it in their existing portal thread.
 */
class SendIntakeConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $caseId) {}

    public function handle(AiAssistService $aiAssist, WebhookDispatcher $webhooks): void
    {
        $case = PatientCase::with(['patient', 'partner', 'clinician.user', 'caseOfferings.offering'])
            ->find($this->caseId);

        if (! $case || ! $case->patient_id) {
            return;
        }

        // Skip if the patient already has an outbound system message for this case
        // (re-assignment shouldn't send a second confirmation).
        $alreadySent = Message::where('case_id', $case->id)
            ->where('direction', 'outbound')
            ->where('sender_type', 'system')
            ->exists();

        if ($alreadySent) {
            return;
        }

        $body = $this->compose($case, $aiAssist);

        $message = Message::create([
            'case_id'      => $case->id,
            'patient_id'   => $case->patient_id,
            'partner_id'   => $case->partner_id,
            'direction'    => 'outbound',
            'channel'      => 'portal',
            'sender_type'  => 'system',
            'body'         => $body,
        ]);

        // Push to the patient portal via Reverb so the message appears immediately
        // without requiring a manual clinician message to trigger a refresh.
        try {
            broadcast(new CaseMessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('A1: Reverb broadcast failed for intake confirmation.', [
                'case_id'    => $case->id,
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);
        }

        // Fire the partner webhook so the tenant portal can notify the patient.
        $webhooks->dispatch($case->partner_id, 'message_created', [
            'case_id'   => $case->uuid,
            'sender'    => 'system',
            'timestamp' => now()->timestamp,
        ]);
    }

    private function compose(PatientCase $case, AiAssistService $aiAssist): string
    {
        // Deterministic fallback — always composed first so the AI path can
        // fall through to this without an extra database call.
        $clinicianName = $case->clinician?->full_name ?? 'your assigned clinician';
        $partner       = $case->partner?->name ?? 'our clinic';
        $offerings     = $case->caseOfferings
            ->pluck('offering.name')
            ->filter()
            ->implode(', ');

        $deterministic = "Hi " . ($case->patient?->first_name ?? 'there') . ",\n\n"
            . "Thank you for submitting your request" . ($offerings ? " for {$offerings}" : '') . " through {$partner}. "
            . "Your case has been received and assigned to {$clinicianName} for clinical review. "
            . "You will hear back from us shortly. In the meantime, if you have any questions or concerns, "
            . "please reply to this message.\n\n"
            . "— The {$partner} Care Team";

        if (! config('ai.karen_enabled', false)) {
            return $deterministic;
        }

        try {
            $draft = $aiAssist->draftPatientReply($case, 'Confirm the patient\'s intake has been received and is under clinical review.');
            return $draft['text'] ?: $deterministic;
        } catch (\Throwable $e) {
            Log::warning('A1: AI intake confirmation draft failed, using deterministic fallback.', [
                'case_id' => $case->id,
                'error'   => $e->getMessage(),
            ]);
            return $deterministic;
        }
    }
}
