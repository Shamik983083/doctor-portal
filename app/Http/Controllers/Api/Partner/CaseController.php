<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\Partner;
use App\Models\Questionnaire;
use App\Models\PatientFile;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Services\CaseStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'patient'                                         => 'required|array',
            'patient.first_name'                              => 'required|string|max:100',
            'patient.last_name'                               => 'required|string|max:100',
            'patient.email'                                   => 'required|email|max:255',
            'patient.phone'                                   => 'nullable|string|max:20',
            'patient.date_of_birth'                           => 'nullable|date',
            'patient.gender'                                  => 'nullable|in:male,female,other',
            'patient.address'                                 => 'nullable|string',
            'patient.city'                                    => 'nullable|string',
            'patient.state'                                   => 'nullable|string|size:2',
            'patient.zip'                                     => 'nullable|string|max:10',
            'patient.external_id'                             => 'nullable|string|max:255',
            'external_id'                                     => 'nullable|string|max:255',
            'hold_status'                                     => 'boolean',
            'is_chargeable'                                   => 'boolean',
            'patient_state'                                   => 'nullable|string|size:2',
            'metadata'                                        => 'nullable|array',
            'offerings'                                       => 'nullable|array',
            'offerings.*.offering_id'                         => 'string',
            'offerings.*.quantity'                            => 'integer|min:1',
            // Grouped questionnaire responses (new format)
            'questionnaire_responses'                         => 'nullable|array',
            'questionnaire_responses.*.questionnaire_id'      => 'required_with:questionnaire_responses|string|exists:questionnaires,uuid',
            'questionnaire_responses.*.answers'               => 'nullable|array',
            'questionnaire_responses.*.answers.*.question_id' => 'required|integer|exists:questionnaire_questions,id',
            'questionnaire_responses.*.answers.*.answer'      => 'nullable',
        ]);

        $partner     = $this->partner($request);
        $patientData = $data['patient'];

        // Deduplicate patient by external_id then email
        $patient = null;
        if (!empty($patientData['external_id'])) {
            $patient = $partner->patients()->where('external_id', $patientData['external_id'])->first();
        }
        if (!$patient) {
            $patient = $partner->patients()->where('email', $patientData['email'])->first();
        }
        if (!$patient) {
            $patient = $partner->patients()->create($patientData);
        } else {
            $patient->update($patientData);
        }

        if (($data['external_id'] ?? null) && $partner->cases()->where('external_id', $data['external_id'])->exists()) {
            return response()->json(['message' => 'Case with this external_id already exists.'], 409);
        }

        $case = DB::transaction(function () use ($data, $partner, $patient, $request) {
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
            $attachedOfferingsIds = [];
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
                        $attachedOfferingsIds[] = $offering->id;
                    }
                }
            }

            // Validate that all required questionnaires for attached offerings are submitted
            if (!empty($attachedOfferingsIds) && !empty($data['questionnaire_responses'])) {
                $submittedQUuids = array_column($data['questionnaire_responses'], 'questionnaire_id');
                $requiredQUuids  = Questionnaire::whereHas('offerings', function ($q) use ($attachedOfferingsIds) {
                    $q->whereIn('offerings.id', $attachedOfferingsIds)
                      ->where('offering_questionnaire.is_required', true);
                })->pluck('uuid')->toArray();

                $missing = array_diff($requiredQUuids, $submittedQUuids);
                if ($missing) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'questionnaire_responses' => 'Required questionnaires not submitted: ' . implode(', ', $missing),
                    ]);
                }
            }

            // Store grouped questionnaire responses with frozen question_text
            if (!empty($data['questionnaire_responses'])) {
                foreach ($data['questionnaire_responses'] as $qrData) {
                    $questionnaire = Questionnaire::where('uuid', $qrData['questionnaire_id'])->first();
                    if (!$questionnaire) continue;

                    $isDisqualified  = false;
                    $disqualifiedOn  = null;

                    // Pre-check answers for disqualification before creating records
                    $questionMap = QuestionnaireQuestion::whereIn(
                        'id',
                        array_column($qrData['answers'] ?? [], 'question_id')
                    )->get()->keyBy('id');

                    foreach ($qrData['answers'] ?? [] as $answerData) {
                        $question = $questionMap[$answerData['question_id']] ?? null;
                        if (!$question) continue;

                        if (\in_array($question->type, ['radio', 'select', 'checkbox', 'choice', 'multi'])) {
                            $options      = $question->options ?? [];
                            $answerValues = (array) ($answerData['answer'] ?? []);
                            foreach ($options as $opt) {
                                if (($opt['is_disqualify'] ?? $opt['disqualifies'] ?? false) && \in_array($opt['value'] ?? $opt['label'], $answerValues)) {
                                    if (!$isDisqualified) {
                                        $disqualifiedOn = $question->key ?: "question_{$question->id}";
                                    }
                                    $isDisqualified = true;
                                }
                            }
                        }
                    }

                    $response = QuestionnaireResponse::create([
                        'questionnaire_id'   => $questionnaire->id,
                        'patient_id'         => $patient->id,
                        'partner_id'         => $partner->id,
                        'case_id'            => $case->id,
                        'external_patient_id'=> $patient->external_id,
                        'is_disqualified'    => $isDisqualified,
                        'disqualified_on'    => $disqualifiedOn,
                        'completed_at'       => now(),
                    ]);

                    foreach ($qrData['answers'] ?? [] as $answerData) {
                        $question = $questionMap[$answerData['question_id']] ?? null;
                        if (!$question) continue;

                        $ansVal = $answerData['answer'] ?? null;

                        // File questions: answer is a file_token UUID from POST /api/partner/files
                        if ($question->type === 'file') {
                            $displayName = '';
                            if ($ansVal) {
                                $fileRecord = PatientFile::where('uuid', $ansVal)
                                    ->where('partner_id', $partner->id)
                                    ->whereNull('case_id')
                                    ->first();
                                if ($fileRecord) {
                                    $fileRecord->update([
                                        'case_id'    => $case->id,
                                        'patient_id' => $patient->id,
                                    ]);
                                    $displayName = $fileRecord->original_name;
                                }
                            }
                            QuestionnaireAnswer::create([
                                'response_id'     => $response->id,
                                'question_id'     => $question->id,
                                'question_text'   => $question->question,
                                'answer'          => $displayName,
                                'is_disqualified' => false,
                            ]);
                            continue;
                        }

                        $ansDisqualify = false;
                        if (\in_array($question->type, ['radio', 'select', 'checkbox', 'choice', 'multi'])) {
                            foreach ($question->options ?? [] as $opt) {
                                if (($opt['is_disqualify'] ?? $opt['disqualifies'] ?? false) && \in_array($opt['value'] ?? $opt['label'], (array) $ansVal)) {
                                    $ansDisqualify = true;
                                    break;
                                }
                            }
                        }

                        QuestionnaireAnswer::create([
                            'response_id'     => $response->id,
                            'question_id'     => $question->id,
                            'question_text'   => $question->question,
                            'answer'          => is_array($ansVal) ? implode(', ', $ansVal) : $ansVal,
                            'is_disqualified' => $ansDisqualify,
                        ]);
                    }
                }
            }

            return $case;
        });

        // Auto-release if no hold
        if (!$case->hold_status) {
            $this->stateMachine->transition($case, PatientCase::STATUS_WAITING);
        }

        return response()->json(
            $case->load(['patient', 'caseOfferings.offering']),
            201
        );
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
