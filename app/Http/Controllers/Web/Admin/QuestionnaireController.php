<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Questionnaire;
use Illuminate\Http\Request;

class QuestionnaireController extends Controller
{
    public function index(Request $request)
    {
        $questionnaires = Questionnaire::with('partner')
            ->withCount('questions')
            ->when($request->input('partner_id'), fn($q, $id) => $q->where('partner_id', $id))
            ->when($request->input('status') !== null && $request->input('status') !== '', fn($q) =>
                $q->where('is_active', $request->input('status') === 'active')
            )
            ->latest()
            ->paginate(25)->withQueryString();

        $partners = Partner::orderBy('name')->get(['id', 'name']);

        return view('admin.questionnaires.index', compact('questionnaires', 'partners'));
    }

    public function create()
    {
        $partners = Partner::orderBy('name')->get(['id', 'name']);
        return view('admin.questionnaires.create', compact('partners'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                              => 'required|string|max:255',
            'description'                       => 'nullable|string',
            'partner_id'                        => 'nullable|exists:partners,id',
            'mode'                              => 'nullable|in:single,multi',
            'questions'                         => 'nullable|array',
            'questions.*.question'              => 'required|string|max:500',
            'questions.*.type'                  => 'required|in:hidden,input,email,textarea,date,select,multiselect,radio,checkbox,file,number,height,weight,bmi',
            'questions.*.key'                   => 'nullable|string|max:100',
            'questions.*.placeholder'           => 'nullable|string|max:255',
            'questions.*.step_number'           => 'nullable|integer|min:1',
            'questions.*.options'               => 'nullable|array',
            'questions.*.options.*.value'       => 'nullable|string|max:255',
        ]);

        $questionnaire = Questionnaire::create([
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
            'partner_id'  => $request->input('partner_id') ?: null,
            'is_active'   => $request->boolean('is_active', true),
            'mode'        => $request->input('mode', 'single'),
        ]);

        $this->syncQuestions($questionnaire, $request->input('questions', []));

        return redirect()->route('admin.questionnaires.show', $questionnaire->id)
            ->with('success', 'Questionnaire created successfully.');
    }

    public function show(int $id)
    {
        $questionnaire = Questionnaire::with(['partner', 'questions'])->findOrFail($id);
        $partners      = Partner::where('status', 'active')->orderBy('name')->get(['id', 'name', 'uuid']);
        return view('admin.questionnaires.show', compact('questionnaire', 'partners'));
    }

    public function edit(int $id)
    {
        $questionnaire = Questionnaire::with('questions')->findOrFail($id);
        $partners = Partner::orderBy('name')->get(['id', 'name']);
        return view('admin.questionnaires.edit', compact('questionnaire', 'partners'));
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'name'                              => 'required|string|max:255',
            'description'                       => 'nullable|string',
            'partner_id'                        => 'nullable|exists:partners,id',
            'mode'                              => 'nullable|in:single,multi',
            'questions'                         => 'nullable|array',
            'questions.*.question'              => 'required|string|max:500',
            'questions.*.type'                  => 'required|in:hidden,input,email,textarea,date,select,multiselect,radio,checkbox,file,number,height,weight,bmi',
            'questions.*.key'                   => 'nullable|string|max:100',
            'questions.*.placeholder'           => 'nullable|string|max:255',
            'questions.*.step_number'           => 'nullable|integer|min:1',
            'questions.*.options'               => 'nullable|array',
            'questions.*.options.*.value'       => 'nullable|string|max:255',
        ]);

        $questionnaire = Questionnaire::findOrFail($id);
        $questionnaire->update([
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
            'partner_id'  => $request->input('partner_id') ?: null,
            'is_active'   => $request->boolean('is_active', true),
            'mode'        => $request->input('mode', 'single'),
        ]);

        $questionnaire->questions()->delete();
        $this->syncQuestions($questionnaire, $request->input('questions', []));

        return redirect()->route('admin.questionnaires.show', $questionnaire->id)
            ->with('success', 'Questionnaire updated successfully.');
    }

    public function destroy(int $id)
    {
        Questionnaire::findOrFail($id)->delete();
        return redirect()->route('admin.questionnaires.index')
            ->with('success', 'Questionnaire deleted.');
    }

    private function syncQuestions(Questionnaire $questionnaire, array $questions): void
    {
        $optionTypes = ['select', 'multiselect', 'radio', 'checkbox'];

        foreach (array_values($questions) as $i => $q) {
            if (empty($q['question'])) continue;

            $type = $q['type'] ?? 'input';
            $options = null;

            if (\in_array($type, $optionTypes, true) && !empty($q['options'])) {
                $options = [];
                foreach (array_values($q['options']) as $opt) {
                    $val = trim($opt['value'] ?? '');
                    if ($val === '') continue;
                    $options[] = [
                        'value'         => $val,
                        'is_disqualify' => !empty($opt['is_disqualify']),
                    ];
                }
                $options = empty($options) ? null : $options;
            }

            $questionnaire->questions()->create([
                'question'    => $q['question'],
                'key'         => $q['key'] ?? null,
                'type'        => $type,
                'placeholder' => $q['placeholder'] ?? null,
                'is_required' => !empty($q['is_required']),
                'is_readonly' => !empty($q['is_readonly']),
                'options'     => $options,
                'sort_order'  => $i,
                'step_number' => (int) ($q['step_number'] ?? 1),
            ]);
        }
    }
}
