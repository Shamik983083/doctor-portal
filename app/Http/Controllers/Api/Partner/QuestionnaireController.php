<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;

class QuestionnaireController extends Controller
{
    public function show(string $uuid)
    {
        $questionnaire = Questionnaire::with([
            'questions'                      => fn($q) => $q->where('is_active', true)->orderBy('step_number')->orderBy('sort_order'),
            'linkedQuestionnaire.questions'  => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ])
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->firstOrFail();

        $mapQuestion = fn($q, string $sourceUuid) => [
            'id'                   => $q->id,
            'slug'                 => $q->slug,
            'question'             => $q->question,
            'key'                  => $q->key,
            'type'                 => $q->type,
            'is_required'          => (bool) $q->is_required,
            'placeholder'          => $q->placeholder,
            'options'              => $q->options,
            'source_questionnaire' => $sourceUuid,
        ];

        $questions = collect();

        // Linked questionnaire questions appear first (Standard Intake 1 pattern)
        if ($questionnaire->linkedQuestionnaire) {
            $linked = $questionnaire->linkedQuestionnaire;
            foreach ($linked->questions as $q) {
                $questions->push($mapQuestion($q, $linked->uuid));
            }
        }

        foreach ($questionnaire->questions as $q) {
            $questions->push($mapQuestion($q, $questionnaire->uuid));
        }

        return response()->json([
            'uuid'                 => $questionnaire->uuid,
            'name'                 => $questionnaire->name,
            'description'          => $questionnaire->description,
            'linked_questionnaire' => $questionnaire->linkedQuestionnaire
                ? ['uuid' => $questionnaire->linkedQuestionnaire->uuid, 'name' => $questionnaire->linkedQuestionnaire->name]
                : null,
            'questions'            => $questions->values(),
        ]);
    }
}
