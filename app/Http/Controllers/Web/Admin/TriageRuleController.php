<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;

class TriageRuleController extends Controller
{
    public function index()
    {
        // Load every active questionnaire with its active questions.
        // We then split by whether the questionnaire has any questions
        // whose options include at least one disqualifying choice.
        $all = Questionnaire::with([
            'questions' => fn($q) => $q->where('is_active', true)
                ->orderBy('step_number')
                ->orderBy('sort_order'),
        ])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Questionnaire $questionnaire): Questionnaire {
                $questionnaire->disqualifierQuestions = $questionnaire->questions
                    ->filter(fn($question) => $this->hasDisqualifyingOption($question->options ?? []))
                    ->values();
                return $questionnaire;
            });

        return view('admin.triage.index', [
            'questionnairesWithRules'    => $all->filter(fn($q) => $q->disqualifierQuestions->isNotEmpty())->values(),
            'questionnairesWithoutRules' => $all->reject(fn($q) => $q->disqualifierQuestions->isNotEmpty())->values(),
            'version'                    => config('triage.version', 'triage-v2'),
            'cfg'                        => config('triage'),
        ]);
    }

    private function hasDisqualifyingOption(array $options): bool
    {
        foreach ($options as $opt) {
            if (! empty($opt['is_disqualify']) || ! empty($opt['disqualifies'])) {
                return true;
            }
        }
        return false;
    }
}
