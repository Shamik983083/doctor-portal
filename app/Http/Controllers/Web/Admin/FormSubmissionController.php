<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\Offering;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\QuestionnaireResponse;
use App\Services\CaseStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormSubmissionController extends Controller
{
    public function __construct(private CaseStateMachine $stateMachine) {}

    public function index(Request $request)
    {
        $submissions = QuestionnaireResponse::with(['questionnaire', 'partner', 'patient'])
            ->whereNull('case_id')
            ->when($request->input('partner_id'), fn($q, $id) => $q->where('partner_id', $id))
            ->when($request->input('status'), function ($q, $status) {
                if ($status === 'disqualified') return $q->where('is_disqualified', true);
                if ($status === 'qualified')    return $q->where('is_disqualified', false);
            })
            ->latest()
            ->paginate(25)->withQueryString();

        $partners = Partner::orderBy('name')->get(['id', 'name']);

        return view('admin.form-submissions.index', compact('submissions', 'partners'));
    }

    public function show(int $id)
    {
        $submission = QuestionnaireResponse::with([
            'questionnaire', 'partner', 'patient',
            'answers',
        ])->whereNull('case_id')->findOrFail($id);

        return view('admin.form-submissions.show', compact('submission'));
    }

    public function createCase(int $id)
    {
        $submission = QuestionnaireResponse::with(['questionnaire', 'partner', 'answers'])
            ->whereNull('case_id')->findOrFail($id);

        $clinicians = Clinician::with('user')
            ->where('status', 'active')
            ->where('is_available', true)
            ->get();

        $offerings = Offering::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']);

        // Try to pre-populate patient fields from keyed answers
        $prefill = [];
        foreach ($submission->answers as $a) {
            if ($a->question_text && $a->answer) {
                $prefill[$a->question_text] = $a->answer;
            }
        }

        // Look for answers with matching question keys via the question relationship
        $keyedAnswers = $submission->answers->mapWithKeys(function ($a) {
            $key = optional($a->question)->key;
            return $key ? [$key => $a->answer] : [];
        });

        return view('admin.form-submissions.create-case', compact(
            'submission', 'clinicians', 'offerings', 'keyedAnswers'
        ));
    }

    public function storeCase(Request $request, int $id)
    {
        $submission = QuestionnaireResponse::whereNull('case_id')->findOrFail($id);

        $request->validate([
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'email'        => 'required|email|max:255',
            'phone'        => 'nullable|string|max:30',
            'date_of_birth'=> 'nullable|date',
            'gender'       => 'nullable|in:male,female,other,prefer_not_to_say',
            'state'        => 'nullable|string|max:2',
            'offering_ids' => 'nullable|array',
            'offering_ids.*'=> 'exists:offerings,id',
            'clinician_id' => 'nullable|exists:clinicians,id',
        ]);

        $caseUuid = null;

        DB::transaction(function () use ($request, $submission, &$caseUuid) {
            // Find or create patient by email within the same partner
            $patient = Patient::firstOrCreate(
                [
                    'email'      => $request->input('email'),
                    'partner_id' => $submission->partner_id,
                ],
                [
                    'first_name'   => $request->input('first_name'),
                    'last_name'    => $request->input('last_name'),
                    'phone'        => $request->input('phone'),
                    'date_of_birth'=> $request->input('date_of_birth'),
                    'gender'       => $request->input('gender'),
                    'state'        => $request->input('state'),
                    'status'       => 'active',
                    'external_id'  => $submission->external_patient_id,
                ]
            );

            $case = PatientCase::create([
                'partner_id'   => $submission->partner_id,
                'patient_id'   => $patient->id,
                'patient_state'=> $request->input('state'),
                'status'       => PatientCase::STATUS_CREATED,
                'external_id'  => $submission->external_patient_id,
            ]);

            // Attach offerings
            if ($request->filled('offering_ids')) {
                foreach ($request->input('offering_ids') as $offeringId) {
                    $case->offerings()->attach($offeringId, ['status' => 'pending', 'quantity' => 1]);
                }
            }

            // Link this form submission to the new case and patient
            $submission->update(['case_id' => $case->id, 'patient_id' => $patient->id]);

            // Assign clinician if selected
            if ($request->filled('clinician_id')) {
                $clinician = Clinician::findOrFail($request->input('clinician_id'));
                $this->stateMachine->transition($case, PatientCase::STATUS_WAITING, ['actor_type' => 'admin']);
                $this->stateMachine->assignToClinician($case, $clinician);
            }

            $caseUuid = $case->uuid;
        });

        return redirect()->route('admin.cases.show', $caseUuid)
            ->with('success', 'Case created and form submission linked successfully.');
    }
}
