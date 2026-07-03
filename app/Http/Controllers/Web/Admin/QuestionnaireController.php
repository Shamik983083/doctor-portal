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
            'linked_questionnaire_id'           => 'nullable|integer|exists:questionnaires,id',
            'questions'                         => 'nullable|array',
            'questions.*.question'              => 'required|string|max:5000',
            'questions.*.type'                  => 'required|in:hidden,input,email,textarea,date,select,multiselect,radio,checkbox,choice,multi,file,number,height,weight,bmi',
            'questions.*.key'                   => 'nullable|string|max:100',
            'questions.*.placeholder'           => 'nullable|string|max:255',
            'questions.*.step_number'           => 'nullable|integer|min:1',
            'questions.*.options'               => 'nullable|array',
            'questions.*.options.*.value'       => 'nullable|string|max:255',
            'questions.*.depends_on_idx'        => 'nullable|integer|min:0',
            'questions.*.depends_on_operator'   => 'nullable|in:equals,not_equals,is_answered,contains',
            'questions.*.depends_on_value'      => 'nullable|string|max:500',
        ]);

        $questionnaire = Questionnaire::create([
            'name'                    => $request->input('name'),
            'description'             => $request->input('description'),
            'partner_id'              => $request->input('partner_id') ?: null,
            'is_active'               => $request->boolean('is_active', true),
            'mode'                    => $request->input('mode', 'single'),
            'purpose'                 => $request->input('purpose', 'clinical'),
            'linked_questionnaire_id' => $request->input('linked_questionnaire_id') ?: null,
        ]);

        $this->syncQuestions($questionnaire, $request->input('questions', []));

        return redirect()->route('admin.questionnaires.show', $questionnaire->id)
            ->with('success', 'Questionnaire created successfully.');
    }

    public function show(int $id)
    {
        $questionnaire = Questionnaire::with(['partner', 'questions.dependsOn'])->findOrFail($id);
        $partners      = Partner::where('status', 'active')->orderBy('name')->get(['id', 'name', 'uuid']);
        return view('admin.questionnaires.show', compact('questionnaire', 'partners'));
    }

    public function edit(int $id)
    {
        $questionnaire  = Questionnaire::with('questions')->findOrFail($id);
        $partners       = Partner::orderBy('name')->get(['id', 'name']);
        $questionnaires = Questionnaire::where('id', '!=', $id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('admin.questionnaires.edit', compact('questionnaire', 'partners', 'questionnaires'));
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'name'                              => 'required|string|max:255',
            'description'                       => 'nullable|string',
            'partner_id'                        => 'nullable|exists:partners,id',
            'mode'                              => 'nullable|in:single,multi',
            'linked_questionnaire_id'           => 'nullable|integer|exists:questionnaires,id',
            'questions'                         => 'nullable|array',
            'questions.*.question'              => 'required|string|max:5000',
            'questions.*.type'                  => 'required|in:hidden,input,email,textarea,date,select,multiselect,radio,checkbox,choice,multi,file,number,height,weight,bmi',
            'questions.*.key'                   => 'nullable|string|max:100',
            'questions.*.placeholder'           => 'nullable|string|max:255',
            'questions.*.step_number'           => 'nullable|integer|min:1',
            'questions.*.options'               => 'nullable|array',
            'questions.*.options.*.value'       => 'nullable|string|max:255',
            'questions.*.depends_on_idx'        => 'nullable|integer|min:0',
            'questions.*.depends_on_operator'   => 'nullable|in:equals,not_equals,is_answered,contains',
            'questions.*.depends_on_value'      => 'nullable|string|max:500',
        ]);

        $questionnaire = Questionnaire::findOrFail($id);
        $questionnaire->update([
            'name'                    => $request->input('name'),
            'description'             => $request->input('description'),
            'partner_id'              => $request->input('partner_id') ?: null,
            'is_active'               => $request->boolean('is_active', true),
            'mode'                    => $request->input('mode', 'single'),
            'purpose'                 => $request->input('purpose', 'clinical'),
            'linked_questionnaire_id' => $request->input('linked_questionnaire_id') ?: null,
        ]);

        // Capture existing slugs keyed by question key before delete so we can restore them
        $preservedSlugs = $questionnaire->questions()->pluck('slug', 'key')->all();

        $questionnaire->questions()->delete();
        $this->syncQuestions($questionnaire, $request->input('questions', []), $preservedSlugs);

        return redirect()->route('admin.questionnaires.show', $questionnaire->id)
            ->with('success', 'Questionnaire updated successfully.');
    }

    public function destroy(int $id)
    {
        Questionnaire::findOrFail($id)->delete();
        return redirect()->route('admin.questionnaires.index')
            ->with('success', 'Questionnaire deleted.');
    }

    private function syncQuestions(Questionnaire $questionnaire, array $questions, array $preservedSlugs = []): void
    {
        $optionTypes = ['select', 'multiselect', 'radio', 'checkbox', 'choice', 'multi'];
        $questions   = array_values($questions);

        // Pass 1 — create all questions without depends_on; capture idx → DB id map
        $idxToId = [];
        foreach ($questions as $i => $q) {
            if (empty($q['question'])) continue;

            $type    = $q['type'] ?? 'input';
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

            // Restore the slug from before the delete (keyed by question key) so stable
            // identifiers survive edits. Null lets the model boot() auto-generate a new one.
            $key  = $q['key'] ?? null;
            $slug = ($key && isset($preservedSlugs[$key])) ? $preservedSlugs[$key] : null;

            $created = $questionnaire->questions()->create([
                'question'    => $q['question'],
                'key'         => $key,
                'slug'        => $slug,
                'type'        => $type,
                'placeholder' => $q['placeholder'] ?? null,
                'is_required' => !empty($q['is_required']),
                'is_readonly' => !empty($q['is_readonly']),
                'options'     => $options,
                'sort_order'  => $i,
                'step_number' => (int) ($q['step_number'] ?? 1),
            ]);

            $idxToId[$i] = $created->id;
        }

        // Pass 2 — resolve depends_on_idx → real question id and persist
        foreach ($questions as $i => $q) {
            if (empty($q['question'])) continue;
            $depIdx    = isset($q['depends_on_idx']) && $q['depends_on_idx'] !== '' ? (int) $q['depends_on_idx'] : null;
            $depOp     = $q['depends_on_operator'] ?? null;
            $depVal    = $q['depends_on_value'] ?? null;

            if ($depIdx !== null && isset($idxToId[$depIdx]) && $depOp) {
                \App\Models\QuestionnaireQuestion::where('id', $idxToId[$i])->update([
                    'depends_on_question_id' => $idxToId[$depIdx],
                    'depends_on_operator'    => $depOp,
                    'depends_on_value'       => ($depOp === 'is_answered') ? null : $depVal,
                ]);
            }
        }
    }
}
