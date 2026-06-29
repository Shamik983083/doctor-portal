<?php

namespace App\Http\Controllers\Web\Form;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use Illuminate\Http\Request;

class QuestionnaireFormController extends Controller
{
    private const OPTION_TYPES = ['select', 'multiselect', 'radio', 'checkbox'];

    public function show(string $uuid)
    {
        $questionnaire = Questionnaire::with([
            'questions' => fn($q) => $q->where('is_active', true),
        ])
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->firstOrFail();

        $grouped    = $questionnaire->questions->groupBy('step_number')->sortKeys();
        $totalSteps = $grouped->count() ?: 1;

        return view('forms.questionnaire', compact('questionnaire', 'grouped', 'totalSteps'));
    }

    public function submit(Request $request, string $uuid)
    {
        $questionnaire = Questionnaire::with([
            'questions' => fn($q) => $q->where('is_active', true),
        ])
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->firstOrFail();

        // Build validation rules dynamically from question definitions
        $rules = [];
        foreach ($questionnaire->questions as $q) {
            $key  = 'answers.' . $q->id;
            $base = $q->is_required ? 'required' : 'nullable';

            $rules[$key] = in_array($q->type, ['multiselect', 'checkbox'])
                ? "{$base}|array"
                : "{$base}|string|max:5000";
        }
        $request->validate($rules);

        $isDisqualified = false;
        $disqualifiedOn = null;
        $answersToSave  = [];

        foreach ($questionnaire->questions as $q) {
            $raw    = $request->input('answers.' . $q->id);
            $answer = is_array($raw) ? json_encode($raw) : ($raw ?? '');
            $disq   = false;

            if (in_array($q->type, self::OPTION_TYPES) && $q->options) {
                $selected = is_array($raw) ? $raw : [$raw];
                foreach ($q->options as $opt) {
                    if (!empty($opt['is_disqualify']) && in_array($opt['value'], $selected, true)) {
                        if (!$isDisqualified) {
                            $isDisqualified = true;
                            $disqualifiedOn = $q->key ?: 'question_' . $q->id;
                        }
                        $disq = true;
                        break;
                    }
                }
            }

            $answersToSave[] = [
                'question_id'    => $q->id,
                'question_text'  => $q->question,
                'answer'         => $answer,
                'is_disqualified'=> $disq,
            ];
        }

        $partner = null;
        if ($request->input('partner_token')) {
            $partner = Partner::where('uuid', $request->input('partner_token'))->first();
        }

        $response = QuestionnaireResponse::create([
            'questionnaire_id'    => $questionnaire->id,
            'partner_id'          => $partner?->id,
            'external_patient_id' => $request->input('external_id'),
            'is_disqualified'     => $isDisqualified,
            'disqualified_on'     => $disqualifiedOn,
            'completed_at'        => now(),
        ]);

        $response->answers()->createMany($answersToSave);

        $postMessagePayload = json_encode([
            'event'           => 'questionnaire_completed',
            'response_token'  => $response->token,
            'disqualified'    => $isDisqualified,
            'disqualified_on' => $disqualifiedOn,
        ]);

        return view('forms.result', [
            'status'             => $isDisqualified ? 'disqualified' : 'success',
            'message'            => $isDisqualified
                ? 'Based on your answers, you do not currently qualify for this service.'
                : 'Thank you. Your responses have been submitted successfully.',
            'postMessagePayload' => $postMessagePayload,
        ]);
    }
}
