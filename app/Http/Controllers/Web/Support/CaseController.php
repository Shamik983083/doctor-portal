<?php

namespace App\Http\Controllers\Web\Support;

use App\Contracts\SmsAdapter;
use App\Events\CaseMessageSent;
use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\Message;
use App\Models\PatientCase;
use App\Services\CaseStateMachine;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CaseController extends Controller
{
    public function __construct(
        private CaseStateMachine  $stateMachine,
        private WebhookDispatcher $webhooks,
        private SmsAdapter        $sms,
    ) {}

    public function index(Request $request)
    {
        $cases = PatientCase::where('status', PatientCase::STATUS_SUPPORT)
            ->with(['patient', 'partner', 'clinician.user', 'caseOfferings.offering'])
            ->when($request->input('search'), fn ($q, $s) =>
                $q->whereHas('patient', fn ($q) =>
                    $q->where('first_name', 'like', "%{$s}%")
                      ->orWhere('last_name', 'like', "%{$s}%")
                )
            )
            ->latest()
            ->paginate(25)->withQueryString();

        return view('support.cases.index', compact('cases'));
    }

    public function show(string $uuid)
    {
        $case = PatientCase::where('status', PatientCase::STATUS_SUPPORT)
            ->with([
                'patient', 'partner', 'clinician.user',
                'messages', 'files',
                'caseOfferings.offering',
                'casePrescriptions.clinician.user',
                'casePrescriptions.medications',
                'questionnaireResponses.questionnaire',
                'questionnaireResponses.answers',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $clinicians = Clinician::with('user')->get();

        return view('support.cases.show', compact('case', 'clinicians'));
    }

    public function sendMessage(Request $request, string $uuid)
    {
        $request->validate(['body' => 'required|string|max:5000']);

        $case = PatientCase::where('status', PatientCase::STATUS_SUPPORT)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $message = Message::create([
            'case_id'     => $case->id,
            'patient_id'  => $case->patient_id,
            'partner_id'  => $case->partner_id,
            'direction'   => 'outbound',
            'channel'     => 'portal',
            'sender_type' => 'support',
            'body'        => $request->input('body'),
        ]);

        try {
            broadcast(new CaseMessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Reverb broadcast failed for support message '.$message->id.': '.$e->getMessage());
        }

        $this->webhooks->dispatch($case->partner_id, 'message_created', [
            'case_id'   => $case->uuid,
            'sender'    => 'support',
            'timestamp' => now()->timestamp,
        ]);

        // A2: SMS nudge to patient when a support agent sends a portal message.
        // Same guards and debounce as the clinician path — see Clinician\CaseController::sendMessage.
        try {
            $patient = $case->patient;
            if ($patient && $patient->phone && $patient->sms_opt_in) {
                $debounceMinutes = (int) config('sms.debounce_minutes', 30);
                $lastOutbound = Message::where('case_id', $case->id)
                    ->where('direction', 'outbound')
                    ->where('channel', 'portal')
                    ->where('id', '!=', $message->id)
                    ->max('created_at');

                $debounced = $lastOutbound
                    && now()->diffInMinutes(\Carbon\Carbon::parse($lastOutbound)) < $debounceMinutes;

                if (!$debounced) {
                    $this->sms->send(
                        $patient->phone,
                        'You have a new message in your patient portal. Please log in to view and reply.'
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SMS notification failed for case ' . $case->id . ': ' . $e->getMessage());
        }

        return back()->with('success', 'Message sent.');
    }

    public function escalate(Request $request, string $uuid)
    {
        $request->validate(['clinician_id' => 'required|exists:clinicians,id']);

        $case = PatientCase::where('status', PatientCase::STATUS_SUPPORT)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $clinician = Clinician::findOrFail($request->input('clinician_id'));

        $this->stateMachine->transition($case, PatientCase::STATUS_ASSIGNED, [
            'clinician_id' => $clinician->id,
            'actor_type'   => 'support',
            'notes'        => $request->input('notes', ''),
        ]);

        return redirect()->route('support.cases.index')
            ->with('success', "Case escalated to {$clinician->full_name}.");
    }
}
