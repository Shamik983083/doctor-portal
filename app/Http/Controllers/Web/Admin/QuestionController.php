<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    private const FIELD_TYPES = [
        'hidden'      => 'Hidden',
        'input'       => 'Input',
        'email'       => 'Email',
        'textarea'    => 'Textarea',
        'date'        => 'Date',
        'select'      => 'Select',
        'multiselect' => 'Multi Select',
        'radio'       => 'Radio',
        'checkbox'    => 'Checkbox',
        'file'        => 'File',
        'number'      => 'Number',
        'height'      => 'Height',
        'weight'      => 'Weight',
        'bmi'         => 'BMI',
    ];

    private const OPTION_TYPES = ['select', 'multiselect', 'radio', 'checkbox'];
    private const PLACEHOLDER_TYPES = ['input', 'email', 'textarea', 'number'];

    public function index(Request $request)
    {
        $questions = QuestionnaireQuestion::with('questionnaire')
            ->when($request->input('type'), fn($q, $t) => $q->where('type', $t))
            ->when($request->input('questionnaire_id'), fn($q, $id) => $q->where('questionnaire_id', $id))
            ->when($request->input('status') !== null && $request->input('status') !== '',
                fn($q) => $q->where('is_active', $request->input('status') === 'active'))
            ->when($request->input('search'), fn($q, $s) =>
                $q->where('question', 'like', "%{$s}%"))
            ->latest()
            ->paginate(25)->withQueryString();

        $questionnaires = Questionnaire::orderBy('name')->get(['id', 'name']);
        $fieldTypes     = self::FIELD_TYPES;

        return view('admin.questions.index', compact('questions', 'questionnaires', 'fieldTypes'));
    }

    public function show(int $id)
    {
        $q = QuestionnaireQuestion::with('questionnaire')->findOrFail($id);

        return response()->json([
            'id'             => $q->id,
            'question'       => $q->question,
            'key'            => $q->key,
            'type'           => $q->type,
            'type_label'     => self::FIELD_TYPES[$q->type] ?? $q->type,
            'placeholder'    => $q->placeholder,
            'is_required'    => $q->is_required,
            'is_readonly'    => $q->is_readonly,
            'is_active'      => $q->is_active,
            'options'        => $q->options ?? [],
            'option_types'   => self::OPTION_TYPES,
            'questionnaire'  => $q->questionnaire ? ['id' => $q->questionnaire->id, 'name' => $q->questionnaire->name] : null,
            'created_at'     => $q->created_at->format('d M Y'),
        ]);
    }

    public function edit(int $id)
    {
        $question       = QuestionnaireQuestion::with('questionnaire')->findOrFail($id);
        $questionnaires = Questionnaire::orderBy('name')->get(['id', 'name']);
        $fieldTypes     = self::FIELD_TYPES;
        $optionTypes    = self::OPTION_TYPES;
        $placeholderTypes = self::PLACEHOLDER_TYPES;

        return view('admin.questions.edit', compact('question', 'questionnaires', 'fieldTypes', 'optionTypes', 'placeholderTypes'));
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'question'               => 'required|string|max:500',
            'key'                    => 'nullable|string|max:100',
            'type'                   => 'required|in:' . implode(',', array_keys(self::FIELD_TYPES)),
            'placeholder'            => 'nullable|string|max:255',
            'questionnaire_id'       => 'required|exists:questionnaires,id',
            'options'                => 'nullable|array',
            'options.*.value'        => 'nullable|string|max:255',
        ]);

        $question = QuestionnaireQuestion::findOrFail($id);

        $options = null;
        if (\in_array($request->input('type'), self::OPTION_TYPES, true) && !empty($request->input('options'))) {
            $options = [];
            foreach (array_values($request->input('options')) as $opt) {
                $val = trim($opt['value'] ?? '');
                if ($val === '') continue;
                $options[] = [
                    'value'         => $val,
                    'is_disqualify' => !empty($opt['is_disqualify']),
                ];
            }
            $options = empty($options) ? null : $options;
        }

        $question->update([
            'questionnaire_id' => $request->input('questionnaire_id'),
            'question'         => $request->input('question'),
            'key'              => $request->input('key'),
            'type'             => $request->input('type'),
            'placeholder'      => $request->input('placeholder'),
            'is_required'      => $request->boolean('is_required'),
            'is_readonly'      => $request->boolean('is_readonly'),
            'is_active'        => $request->boolean('is_active', true),
            'options'          => $options,
        ]);

        return redirect()->route('admin.questions.index')
            ->with('success', 'Question updated successfully.');
    }

    public function destroy(int $id)
    {
        QuestionnaireQuestion::findOrFail($id)->delete();
        return redirect()->route('admin.questions.index')
            ->with('success', 'Question deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        QuestionnaireQuestion::whereIn('id', $request->input('ids'))->delete();
        return redirect()->route('admin.questions.index')
            ->with('success', count($request->input('ids')) . ' question(s) deleted.');
    }

    public function toggleStatus(Request $request, int $id)
    {
        $question = QuestionnaireQuestion::findOrFail($id);
        $question->update(['is_active' => !$question->is_active]);
        return response()->json(['is_active' => $question->is_active]);
    }
}
