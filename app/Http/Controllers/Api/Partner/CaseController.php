<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\Partner;
use App\Services\CaseStateMachine;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    public function __construct(private CaseStateMachine $stateMachine) {}

    private function partner(Request $request): Partner
    {
        return $request->attributes->get('partner');
    }

    public function index(Request $request)
    {
        $cases = $this->partner($request)->cases()
            ->with(['patient', 'clinician.user', 'caseOfferings.offering'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->patient_id, fn($q, $id) => $q->whereHas('patient', fn($q) => $q->where('uuid', $id)))
            ->latest()
            ->paginate(25);

        return response()->json($cases);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id'     => 'required|string',
            'external_id'    => 'nullable|string|max:255',
            'hold_status'    => 'boolean',
            'is_chargeable'  => 'boolean',
            'patient_state'  => 'nullable|string|size:2',
            'metadata'       => 'nullable|array',
            'offerings'      => 'nullable|array',
            'offerings.*.offering_id' => 'string',
            'offerings.*.quantity'    => 'integer|min:1',
            'questions'      => 'nullable|array',
            'questions.*.question' => 'string',
            'questions.*.answer'   => 'nullable|string',
        ]);

        $partner = $this->partner($request);
        $patient = $partner->patients()->where('uuid', $data['patient_id'])->firstOrFail();

        if (($data['external_id'] ?? null) && $partner->cases()->where('external_id', $data['external_id'])->exists()) {
            return response()->json(['message' => 'Case with this external_id already exists.'], 409);
        }

        $case = $partner->cases()->create([
            'patient_id'    => $patient->id,
            'external_id'   => $data['external_id'] ?? null,
            'hold_status'   => $data['hold_status'] ?? false,
            'is_chargeable' => $data['is_chargeable'] ?? true,
            'patient_state' => $data['patient_state'] ?? $patient->state,
            'metadata'      => $data['metadata'] ?? null,
            'status'        => PatientCase::STATUS_CREATED,
        ]);

        // Attach offerings
        if (!empty($data['offerings'])) {
            foreach ($data['offerings'] as $offeringData) {
                $offering = $partner->offerings()
                    ->where('uuid', $offeringData['offering_id'])
                    ->first();
                if ($offering) {
                    $case->caseOfferings()->create([
                        'offering_id' => $offering->id,
                        'quantity'    => $offeringData['quantity'] ?? 1,
                        'price'       => $offeringData['price'] ?? $offering->price,
                    ]);
                }
            }
        }

        // Attach questions
        if (!empty($data['questions'])) {
            foreach ($data['questions'] as $i => $q) {
                $case->caseQuestions()->create([
                    'question'   => $q['question'],
                    'answer'     => $q['answer'] ?? null,
                    'sort_order' => $i,
                ]);
            }
        }

        // Auto-release if no hold
        if (!$case->hold_status) {
            $this->stateMachine->transition($case, PatientCase::STATUS_WAITING);
        }

        return response()->json($case->load(['patient', 'caseOfferings.offering', 'caseQuestions']), 201);
    }

    public function show(Request $request, string $id)
    {
        $case = $this->partner($request)->cases()
            ->with(['patient', 'clinician.user', 'caseOfferings.offering', 'caseQuestions', 'diseases', 'orders', 'clinicalNotes', 'tags'])
            ->where('uuid', $id)->firstOrFail();

        return response()->json($case);
    }

    public function showByExternalId(Request $request, string $externalId)
    {
        $case = $this->partner($request)->cases()
            ->where('external_id', $externalId)->firstOrFail();

        return response()->json($case->load(['patient', 'caseOfferings.offering']));
    }

    public function cancel(Request $request, string $id)
    {
        $request->validate(['reason' => 'nullable|string']);

        $case = $this->partner($request)->cases()->where('uuid', $id)->firstOrFail();

        $this->stateMachine->cancel($case, $request->reason ?? '');

        return response()->json(['message' => 'Case cancelled.', 'case' => $case->fresh()]);
    }

    public function processing(Request $request, string $id)
    {
        $case = $this->partner($request)->cases()->where('uuid', $id)->firstOrFail();

        $this->stateMachine->startProcessing($case);

        return response()->json(['message' => 'Case moved to processing.', 'case' => $case->fresh()]);
    }

    public function setHold(Request $request, string $id)
    {
        $request->validate(['hold' => 'required|boolean']);

        $case = $this->partner($request)->cases()->where('uuid', $id)->firstOrFail();

        if (!$request->hold && $case->hold_status) {
            $this->stateMachine->release($case);
        } else {
            $case->update(['hold_status' => $request->hold]);
        }

        return response()->json($case->fresh());
    }

    public function support(Request $request, string $id)
    {
        $request->validate(['note' => 'nullable|string']);

        $case = $this->partner($request)->cases()->where('uuid', $id)->firstOrFail();

        $this->stateMachine->escalateToSupport($case, $request->note ?? '');

        return response()->json($case->fresh());
    }

    public function events(Request $request, string $id)
    {
        $case = $this->partner($request)->cases()->where('uuid', $id)->firstOrFail();

        return response()->json($case->events()->latest()->get());
    }
}
