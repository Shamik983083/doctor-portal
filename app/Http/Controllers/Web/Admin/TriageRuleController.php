<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use Illuminate\Http\Request;

class TriageRuleController extends Controller
{
    public function index()
    {
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
                    ->filter(fn($q) => $this->hasDisqualifyingOption($q->options ?? []))
                    ->values();
                return $questionnaire;
            });

        return view('admin.triage.index', [
            'questionnairesWithRules'    => $all->filter(fn($q) => $q->disqualifierQuestions->isNotEmpty())->values(),
            'questionnairesWithoutRules' => $all->reject(fn($q) => $q->disqualifierQuestions->isNotEmpty())->values(),
            'allQuestionnaires'          => $all,
            'version'                    => config('triage.version', 'triage-v2'),
            'cfg'                        => config('triage'),
        ]);
    }

    /** Mark a new option value on a question as disqualifying (adds it if it doesn't exist). */
    public function storeOption(Request $request, QuestionnaireQuestion $questionnaireQuestion)
    {
        $data = $request->validate([
            'option_value' => 'required|string|max:255',
        ]);

        $options  = $questionnaireQuestion->options ?? [];
        $found    = false;

        $options = collect($options)->map(function ($opt) use ($data, &$found) {
            if (($opt['value'] ?? '') === $data['option_value']) {
                $opt['is_disqualify'] = true;
                $found = true;
            }
            return $opt;
        })->all();

        if (! $found) {
            $options[] = ['value' => $data['option_value'], 'is_disqualify' => true];
        }

        $questionnaireQuestion->update(['options' => $options]);

        return back()->with('success', 'Disqualifier rule added.');
    }

    /** Toggle is_disqualify on a specific option value. */
    public function toggleOption(Request $request, QuestionnaireQuestion $questionnaireQuestion)
    {
        $data = $request->validate(['option_value' => 'required|string']);

        $options = collect($questionnaireQuestion->options ?? [])->map(function ($opt) use ($data) {
            if (($opt['value'] ?? '') === $data['option_value']) {
                $opt['is_disqualify'] = ! ($opt['is_disqualify'] ?? false);
            }
            return $opt;
        })->all();

        $questionnaireQuestion->update(['options' => $options]);

        return back()->with('success', 'Rule toggled.');
    }

    /** Rename a disqualifying option value. */
    public function updateOption(Request $request, QuestionnaireQuestion $questionnaireQuestion)
    {
        $data = $request->validate([
            'old_value' => 'required|string',
            'new_value' => 'required|string|max:255',
        ]);

        $options = collect($questionnaireQuestion->options ?? [])->map(function ($opt) use ($data) {
            if (($opt['value'] ?? '') === $data['old_value']) {
                $opt['value'] = $data['new_value'];
            }
            return $opt;
        })->all();

        $questionnaireQuestion->update(['options' => $options]);

        return back()->with('success', 'Rule updated.');
    }

    /** Remove the disqualify flag from a specific option (does not delete the option itself). */
    public function destroyOption(Request $request, QuestionnaireQuestion $questionnaireQuestion)
    {
        $data = $request->validate(['option_value' => 'required|string']);

        $options = collect($questionnaireQuestion->options ?? [])->map(function ($opt) use ($data) {
            if (($opt['value'] ?? '') === $data['option_value']) {
                $opt['is_disqualify'] = false;
            }
            return $opt;
        })->all();

        $questionnaireQuestion->update(['options' => $options]);

        return back()->with('success', 'Disqualifier rule removed.');
    }

    // ────────────────────────────────────────────────────────────────────────

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
