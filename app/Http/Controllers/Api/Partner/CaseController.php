<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\Partner;
use App\Models\PartnerProductPlan;
use App\Models\Questionnaire;
use App\Models\PatientFile;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Services\CaseStateMachine;
use App\Services\CheckInQuestionnaireResolver;
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
            'patient.height'                                  => 'required|numeric|min:0',
            'patient.weight'                                  => 'required|numeric|min:0',
            'patient.bmi'                                     => 'required|numeric|min:0',
            'patient.address'                                 => 'nullable|string',
            'patient.city'                                    => 'nullable|string',
            'patient.state'                                   => 'nullable|string|size:2',
            'patient.zip'                                     => 'nullable|string|max:10',
            'patient.external_id'                             => 'nullable|string|max:255',
            'patient.id_verified_status'                      => 'nullable|in:verified,failed,pending',
            'patient.id_verified_at'                          => 'nullable|date',
            'external_id'                                     => 'nullable|string|max:255',
            'visit_type'                                      => 'nullable|string|max:100',
            /*
             * Re-bill / check-in, NOT a pharmacy refill (Devin msg 2246). Set it
             * and the case routes back to the doctor who treated this patient
             * before, and counts as a check-in rather than a first visit in
             * reporting.
             *
             * Optional, and false is the safe default: a check-in arriving
             * unflagged just routes normally, whereas a first visit wrongly
             * flagged would be handed to a doctor on the strength of a history
             * that does not apply. Partners not sending it yet may still be
             * picked up by the visit_type fallback, see
             * PatientCase::isRefillRequest().
             */
            'is_refill'                                       => 'nullable|boolean',
            'hold_status'                                     => 'boolean',
            'is_chargeable'                                   => 'boolean',
            'patient_state'                                   => 'nullable|string|size:2',
            'metadata'                                        => 'nullable|array',
            /*
             * Clinical intake block (Devin msg 2258, "they send to us", "exact
             * format" from the design preview). Populates the provider review
             * queue's medication columns. Every field optional: a storefront can
             * send all of it, some, or none, and the queue shows a dash for
             * anything missing rather than a fabricated value. See
             * PatientCase::queueClinical() and docs/integrations/STOREFRONT-INTAKE.md.
             */
            'clinical_intake'                                 => 'nullable|array',
            'clinical_intake.product'                         => 'nullable|string|max:120',
            'clinical_intake.dose'                            => 'nullable|string|max:60',
            'clinical_intake.term'                            => 'nullable|string|max:30',
            'clinical_intake.plan'                            => 'nullable|string|max:40',
            'clinical_intake.med2'                            => 'nullable|string|max:120',
            'clinical_intake.med3'                            => 'nullable|string|max:120',
            'clinical_intake.med4'                            => 'nullable|string|max:120',
            'clinical_intake.onGlp'                           => 'nullable|string|max:8',
            'clinical_intake.zofran'                          => 'nullable|string|max:8',
            'clinical_intake.allergy'                         => 'nullable|string|max:8',
            'clinical_intake.allergyDetail'                   => 'nullable|string|max:500',
            'clinical_intake.video'                           => 'nullable|string|max:20',
            'clinical_intake.protocolVersion'                 => 'nullable|string|max:60',
            'clinical_intake.findings'                        => 'nullable|array',
            'clinical_intake.summary'                         => 'nullable|array',
            'clinical_intake.sourceAnswers'                   => 'nullable|array',
            'offerings'                                       => 'nullable|array',
            'offerings.*.offering_id'                         => 'nullable|string',
            'offerings.*.product_key'                         => 'nullable|string|max:120',
            'offerings.*.month_frequency'                     => 'nullable|integer|min:1|max:24',
            'offerings.*.quantity'                            => 'integer|min:1',
            // Simplified flat answers (new path — questionnaire derived from offering)
            'answers'                                         => 'nullable|array',
            'answers.*.slug'                                  => 'required_with:answers|string|max:120',
            'answers.*.answer'                                => 'nullable',
            // Grouped questionnaire responses (legacy path — still fully supported)
            'questionnaire_responses'                         => 'nullable|array',
            'questionnaire_responses.*.questionnaire_id'      => 'required_with:questionnaire_responses|string|exists:questionnaires,uuid',
            'questionnaire_responses.*.answers'               => 'nullable|array',
            'questionnaire_responses.*.answers.*.question_id' => 'nullable|integer|exists:questionnaire_questions,id',
            'questionnaire_responses.*.answers.*.slug'        => 'nullable|string|max:120',
            'questionnaire_responses.*.answers.*.answer'      => 'nullable',
        ]);

        $partner     = $this->partner($request);
        $patientData = $data['patient'];

        // ── Pre-resolve offerings (Path A + Path B fan-out) ──────────────────
        // Done before questionnaire resolution so buildResponsesFromOfferings
        // always receives valid offering UUIDs regardless of which path was used.
        //
        // Path A (legacy): offering_id sent directly — one item in, one item out.
        // Path B (new):    product_key + month_frequency → ALL matching plan rows
        //                  are resolved. One submitted item fans out to N offerings,
        //                  each becoming its own case_offering row. This is the
        //                  one-to-many model: e.g. "semaglutide" 3M resolves to
        //                  SNAC, B12, and B6 variants simultaneously.
        //
        // $expandedOfferings replaces $data['offerings'] before questionnaire
        // resolution. $resolvedOfferings[i] is the Offering model at position i
        // in $expandedOfferings — reused by the transaction block so offerings are
        // never queried twice.
        $effectiveState    = $data['patient_state'] ?? $patientData['state'] ?? null;
        $expandedOfferings = [];
        $resolvedOfferings = [];

        foreach ($data['offerings'] ?? [] as $offeringData) {

            // Path B: product_key + month_frequency → fan-out to all plan rows
            if (!empty($offeringData['product_key']) && !empty($offeringData['month_frequency'])) {
                $plans = PartnerProductPlan::where('partner_id', $partner->id)
                    ->where('product_key', $offeringData['product_key'])
                    ->where('month_frequency', (int) $offeringData['month_frequency'])
                    ->get();

                if ($plans->isEmpty()) {
                    return response()->json([
                        'message' => "No product plan found for product_key \"{$offeringData['product_key']}\" with month_frequency {$offeringData['month_frequency']}.",
                        'errors'  => ['offerings' => ["Product plan not found: product_key \"{$offeringData['product_key']}\", month_frequency {$offeringData['month_frequency']}."]],
                    ], 422);
                }

                $resolvedCount = 0;
                foreach ($plans as $plan) {
                    $offering = $partner->accessibleOfferings()
                        ->where('offerings.id', $plan->offering_id)
                        ->first();

                    if (!$offering) continue; // offering deleted or access revoked — skip silently

                    // State availability gate
                    if ($effectiveState && !$offering->isAvailableInState($effectiveState)) {
                        return response()->json([
                            'message' => "Offering \"{$offering->name}\" is not available in state {$effectiveState}.",
                            'errors'  => ['offerings' => ["Offering \"{$offering->name}\" is not available in state {$effectiveState}."]],
                        ], 422);
                    }

                    // Category gate (unroutable without a category)
                    if ($offering->category_id === null) {
                        return response()->json([
                            'message' => "Offering \"{$offering->name}\" has no product category configured and cannot be routed. Contact the platform administrator.",
                            'errors'  => ['offerings' => ["Offering \"{$offering->name}\" has no product category configured."]],
                        ], 422);
                    }

                    $entry                = $offeringData;
                    $entry['offering_id'] = $offering->uuid; // inject UUID for questionnaire resolution
                    $expandedOfferings[]  = $entry;
                    $resolvedOfferings[]  = $offering;
                    $resolvedCount++;
                }

                if ($resolvedCount === 0) {
                    return response()->json([
                        'message' => "No accessible offerings found for product_key \"{$offeringData['product_key']}\" with month_frequency {$offeringData['month_frequency']}.",
                        'errors'  => ['offerings' => ["All plan offerings for \"{$offeringData['product_key']}\" ({$offeringData['month_frequency']}M) are inaccessible for this partner."]],
                    ], 422);
                }

            // Path A (legacy): direct offering_id — one item in, one item out
            } elseif (!empty($offeringData['offering_id'])) {
                $offering = $partner->accessibleOfferings()
                    ->where('offerings.uuid', $offeringData['offering_id'])
                    ->first();

                if (!$offering) continue; // unresolved — silently skip (mirrors prior behaviour)

                // State availability gate
                if ($effectiveState && !$offering->isAvailableInState($effectiveState)) {
                    return response()->json([
                        'message' => "Offering \"{$offering->name}\" is not available in state {$effectiveState}.",
                        'errors'  => ['offerings' => ["Offering \"{$offering->name}\" is not available in state {$effectiveState}."]],
                    ], 422);
                }

                // Category gate
                if ($offering->category_id === null) {
                    return response()->json([
                        'message' => "Offering \"{$offering->name}\" has no product category configured and cannot be routed. Contact the platform administrator.",
                        'errors'  => ['offerings' => ["Offering \"{$offering->name}\" has no product category configured."]],
                    ], 422);
                }

                $expandedOfferings[] = $offeringData;
                $resolvedOfferings[] = $offering;
            }
        }

        $data['offerings'] = $expandedOfferings;
        // ─────────────────────────────────────────────────────────────────────

        // ── Resolve questionnaire responses ───────────────────────────────────
        // Path A (new): flat top-level `answers` array — derive questionnaires
        //   from the submitted offerings via the offering_questionnaire pivot.
        //   Output is already split by questionnaire with question_ids resolved.
        // Path B (legacy): `questionnaire_responses` array — resolve slugs and
        //   split linked questionnaires exactly as before. Fully backward compat.
        if (!empty($data['answers']) && empty($data['questionnaire_responses'])) {
            $data['questionnaire_responses'] = $this->buildResponsesFromOfferings(
                $data['answers'],
                $data['offerings'] ?? [],
                $partner,
                (bool) ($data['is_refill'] ?? false)
            );
        } elseif (!empty($data['questionnaire_responses'])) {
            $expanded = [];
            foreach ($data['questionnaire_responses'] as $qrData) {
                $questionnaire = Questionnaire::with([
                    'questions',
                    'linkedQuestionnaire.questions',
                ])->where('uuid', $qrData['questionnaire_id'])->first();

                if (!$questionnaire) { $expanded[] = $qrData; continue; }

                $slugToQuestion    = [];
                $idToQuestionnaire = [];
                foreach ($questionnaire->questions as $q) {
                    if ($q->slug) $slugToQuestion[$q->slug] = $q;
                    $idToQuestionnaire[$q->id] = $questionnaire;
                }
                $linked = $questionnaire->linkedQuestionnaire;
                if ($linked) {
                    foreach ($linked->questions as $q) {
                        if ($q->slug) $slugToQuestion[$q->slug] = $q;
                        $idToQuestionnaire[$q->id] = $linked;
                    }
                }

                $resolvedAnswers = [];
                foreach ($qrData['answers'] ?? [] as $answer) {
                    if (empty($answer['question_id']) && !empty($answer['slug'])) {
                        $match = $slugToQuestion[$answer['slug']] ?? null;
                        if ($match) $answer['question_id'] = $match->id;
                    }
                    if (!empty($answer['question_id'])) $resolvedAnswers[] = $answer;
                }

                if ($linked) {
                    $mainAnswers = []; $linkedAnswers = [];
                    foreach ($resolvedAnswers as $answer) {
                        $owner = $idToQuestionnaire[$answer['question_id']] ?? null;
                        if ($owner && $owner->id === $linked->id) $linkedAnswers[] = $answer;
                        else $mainAnswers[] = $answer;
                    }
                    $expanded[] = ['questionnaire_id' => $linked->uuid,               'answers' => $linkedAnswers];
                    $expanded[] = ['questionnaire_id' => $qrData['questionnaire_id'], 'answers' => $mainAnswers];
                } else {
                    $expanded[] = ['questionnaire_id' => $qrData['questionnaire_id'], 'answers' => $resolvedAnswers];
                }
            }
            $data['questionnaire_responses'] = $expanded;
        }
        unset($data['answers']);
        // ─────────────────────────────────────────────────────────────────────

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

        // E19: copy the partner's collaborating clinician default onto the patient
        // if the patient does not already have one. Never overwrites an existing
        // assignment — the partner default is a first-time convenience, not a rule.
        if ($partner->collaborating_clinician_id && !$patient->collaborating_clinician_id) {
            $patient->update(['collaborating_clinician_id' => $partner->collaborating_clinician_id]);
        }

        if (($data['external_id'] ?? null) && $partner->cases()->where('external_id', $data['external_id'])->exists()) {
            return response()->json(['message' => 'Case with this external_id already exists.'], 409);
        }

        $case = DB::transaction(function () use ($data, $partner, $patient, $request, $resolvedOfferings) {
            $case = $partner->cases()->create([
                'patient_id'    => $patient->id,
                'external_id'   => $data['external_id'] ?? null,
                'visit_type'    => $data['visit_type'] ?? null,
                'is_refill'     => $data['is_refill'] ?? false,
                'clinical_intake' => $data['clinical_intake'] ?? null,
                'hold_status'   => $data['hold_status'] ?? false,
                'is_chargeable' => $data['is_chargeable'] ?? true,
                'patient_state' => $data['patient_state'] ?? $patient->state,
                'metadata'      => $data['metadata'] ?? null,
                'status'        => PatientCase::STATUS_CREATED,
            ]);

            // Attach offerings — use pre-resolved map; no extra queries
            $attachedOfferingsIds = [];
            if (!empty($data['offerings'])) {
                foreach ($data['offerings'] as $idx => $offeringData) {
                    $offering = $resolvedOfferings[$idx] ?? null;
                    if ($offering) {
                        $case->caseOfferings()->create([
                            'offering_id'     => $offering->id,
                            'quantity'        => $offeringData['quantity'] ?? 1,
                            'price'           => $offeringData['price'] ?? $offering->price,
                            'month_frequency' => isset($offeringData['month_frequency']) ? (int) $offeringData['month_frequency'] : null,
                            'product_key'     => $offeringData['product_key'] ?? null,
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
            ->with(['patient', 'clinician.user', 'caseOfferings.offering', 'caseQuestions', 'diseases', 'orders', 'clinicalNotes', 'tags', 'casePrescription.medications', 'casePrescription.diagnosesCodes'])
            ->where('uuid', $id)->firstOrFail();

        return response()->json($case);
    }

    /**
     * Push (or replace) the clinical intake block for a case (Devin msg 2258).
     *
     * POST /api/partner/cases/{id}/clinical. For storefronts that learn the
     * medication detail after the case is already created, or want to update it,
     * rather than only at create time. Partner-scoped through $this->partner(),
     * so a storefront can only write to its own cases.
     *
     * REPLACES the block wholesale rather than merging: the storefront owns this
     * data and the queue should reflect exactly what they last sent, not a merge
     * of two intake snapshots. Same field set and same optionality as create.
     */
    public function updateClinical(Request $request, string $id)
    {
        $data = $request->validate([
            'clinical_intake'                 => 'required|array',
            'clinical_intake.product'         => 'nullable|string|max:120',
            'clinical_intake.dose'            => 'nullable|string|max:60',
            'clinical_intake.term'            => 'nullable|string|max:30',
            'clinical_intake.plan'            => 'nullable|string|max:40',
            'clinical_intake.med2'            => 'nullable|string|max:120',
            'clinical_intake.med3'            => 'nullable|string|max:120',
            'clinical_intake.med4'            => 'nullable|string|max:120',
            'clinical_intake.onGlp'           => 'nullable|string|max:8',
            'clinical_intake.zofran'          => 'nullable|string|max:8',
            'clinical_intake.allergy'         => 'nullable|string|max:8',
            'clinical_intake.allergyDetail'   => 'nullable|string|max:500',
            'clinical_intake.video'           => 'nullable|string|max:20',
            'clinical_intake.protocolVersion' => 'nullable|string|max:60',
            'clinical_intake.findings'        => 'nullable|array',
            'clinical_intake.summary'         => 'nullable|array',
            'clinical_intake.sourceAnswers'   => 'nullable|array',
        ]);

        $case = $this->partner($request)->cases()->where('uuid', $id)->firstOrFail();

        $case->update(['clinical_intake' => $data['clinical_intake']]);

        return response()->json([
            'message' => 'Clinical intake updated.',
            'case'    => $case->fresh(['patient', 'caseOfferings.offering']),
        ]);
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

    public function returnToClinician(Request $request, string $id)
    {
        $request->validate(['partner_note' => 'required|string|max:1000']);

        $case = $this->partner($request)->cases()
            ->where('uuid', $id)
            ->whereNotNull('support_at')
            ->where('status', 'support')
            ->firstOrFail();

        $partnerNote = $request->input('partner_note');

        $this->stateMachine->returnToClinicianFromSupport($case, $partnerNote);

        if ($case->clinician_id) {
            \App\Models\ClinicalNote::create([
                'case_id'      => $case->id,
                'clinician_id' => $case->clinician_id,
                'type'         => 'general',
                'note'         => 'Support response: ' . $partnerNote,
                'is_private'   => false,
            ]);
        }

        return response()->json(['message' => 'Case returned to clinician.', 'case' => $case->fresh()]);
    }

    public function events(Request $request, string $id)
    {
        $case = $this->partner($request)->cases()->where('uuid', $id)->firstOrFail();

        return response()->json($case->events()->latest()->get());
    }

    /**
     * Build questionnaire_responses from a flat answers array by resolving
     * each slug against the questionnaires attached to the submitted offerings.
     * Returns the same structure the downstream pipeline expects, with
     * question_id already set and answers already split by questionnaire.
     *
     * For refill cases ($isRefill = true) check-in questionnaires are indexed
     * first so their slugs win over clinical ones. If no check-in questionnaire
     * is configured anywhere, the clinical questionnaire is used as a fallback —
     * refills never break.
     */
    private function buildResponsesFromOfferings(array $rawAnswers, array $offeringsData, Partner $partner, bool $isRefill = false): array
    {
        if (empty($rawAnswers) || empty($offeringsData)) return [];

        $offeringUuids = array_column($offeringsData, 'offering_id');

        $eagerLoads = [
            'questionnaires.questions'                     => fn($q) => $q->where('is_active', true),
            'questionnaires.linkedQuestionnaire.questions' => fn($q) => $q->where('is_active', true),
        ];

        // For refill cases, also load the category's check-in questionnaire and
        // its questions so we can prioritise check-in slugs over clinical ones.
        if ($isRefill) {
            $eagerLoads['category.checkInQuestionnaire.questions'] = fn($q) => $q->where('is_active', true);
        }

        $offerings = $partner->accessibleOfferings()
            ->whereIn('offerings.uuid', $offeringUuids)
            ->with($eagerLoads)
            ->get();

        $slugToQuestion    = []; // slug => QuestionnaireQuestion
        $idToQuestionnaire = []; // question_id => Questionnaire
        $seenIds           = [];

        // Pass 1 (refill only): index check-in questionnaire questions first so
        // they win when a slug exists in both the check-in and clinical forms.
        if ($isRefill) {
            foreach ($offerings as $offering) {
                // Per-offering check-in (highest priority)
                foreach ($offering->questionnaires->where('purpose', 'check_in') as $questionnaire) {
                    if (! $questionnaire->is_active || in_array($questionnaire->id, $seenIds)) continue;
                    $seenIds[] = $questionnaire->id;
                    foreach ($questionnaire->questions as $q) {
                        if ($q->slug) $slugToQuestion[$q->slug] = $q;
                        $idToQuestionnaire[$q->id] = $questionnaire;
                    }
                }
                // Category default check-in
                $catQ = $offering->category?->checkInQuestionnaire;
                if ($catQ && $catQ->is_active && ! in_array($catQ->id, $seenIds)) {
                    $seenIds[] = $catQ->id;
                    foreach ($catQ->questions as $q) {
                        if ($q->slug) $slugToQuestion[$q->slug] = $q;
                        $idToQuestionnaire[$q->id] = $catQ;
                    }
                }
            }
        }

        // Pass 2: index clinical questionnaires (always; serves as fallback for refills)
        foreach ($offerings as $offering) {
            foreach ($offering->questionnaires->where('purpose', '!=', 'check_in') as $questionnaire) {
                // Index linked questionnaire questions first (e.g. Standard Intake 1)
                $linked = $questionnaire->linkedQuestionnaire;
                if ($linked && !in_array($linked->id, $seenIds)) {
                    $seenIds[] = $linked->id;
                    foreach ($linked->questions as $q) {
                        if ($q->slug) $slugToQuestion[$q->slug] = $q;
                        $idToQuestionnaire[$q->id] = $linked;
                    }
                }
                if (!in_array($questionnaire->id, $seenIds)) {
                    $seenIds[] = $questionnaire->id;
                    foreach ($questionnaire->questions as $q) {
                        if ($q->slug) $slugToQuestion[$q->slug] = $q;
                        $idToQuestionnaire[$q->id] = $questionnaire;
                    }
                }
            }
        }

        // Group answers by their owning questionnaire
        $grouped = []; // uuid => [answers]
        foreach ($rawAnswers as $answer) {
            $slug = $answer['slug'] ?? null;
            if (!$slug) continue;
            $question      = $slugToQuestion[$slug] ?? null;
            if (!$question) continue;
            $questionnaire = $idToQuestionnaire[$question->id] ?? null;
            if (!$questionnaire) continue;

            $grouped[$questionnaire->uuid][] = [
                'question_id' => $question->id,
                'answer'      => $answer['answer'] ?? null,
            ];
        }

        $responses = [];
        foreach ($grouped as $uuid => $answers) {
            $responses[] = ['questionnaire_id' => $uuid, 'answers' => $answers];
        }

        return $responses;
    }
}
