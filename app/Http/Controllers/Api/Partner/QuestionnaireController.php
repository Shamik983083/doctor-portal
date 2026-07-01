<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use Illuminate\Http\Request;

class QuestionnaireController extends Controller
{
    public function show(Request $request, string $uuid)
    {
        $questionnaire = Questionnaire::with(['questions' => fn($q) => $q->orderBy('sort_order')])
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'uuid'        => $questionnaire->uuid,
            'name'        => $questionnaire->name,
            'description' => $questionnaire->description,
            'questions'   => $questionnaire->questions->map(fn($q) => [
                'id'          => $q->id,
                'question'    => $q->question,
                'key'         => $q->key,
                'type'        => $q->type,
                'is_required' => (bool) $q->is_required,
                'placeholder' => $q->placeholder,
                'options'     => $q->options,
            ])->values(),
        ]);
    }
}
