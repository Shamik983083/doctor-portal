<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfferingCategory;
use App\Models\Questionnaire;
use Illuminate\Http\Request;

class OfferingCategoryController extends Controller
{
    public function index()
    {
        $categories = OfferingCategory::with('checkInQuestionnaire.partner')
            ->withCount('offerings')
            ->latest()
            ->get();

        $checkInQuestionnaires = Questionnaire::with('partner')
            ->where('purpose', 'check_in')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'partner_id']);

        return view('admin.categories.index', compact('categories', 'checkInQuestionnaires'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                       => 'required|string|max:150|unique:offering_categories,name',
            'description'                => 'nullable|string|max:1000',
            'check_in_questionnaire_id'  => 'nullable|exists:questionnaires,id',
        ]);

        $data['is_active'] = true;
        OfferingCategory::create($data);

        return back()->with('success', "Category \"{$data['name']}\" created.");
    }

    public function updateCheckIn(Request $request, OfferingCategory $category)
    {
        $request->validate([
            'check_in_questionnaire_id' => 'nullable|exists:questionnaires,id',
        ]);

        $category->update([
            'check_in_questionnaire_id' => $request->input('check_in_questionnaire_id') ?: null,
        ]);

        $qName = $category->checkInQuestionnaire?->name ?? 'none';
        return back()->with('success', "Check-in questionnaire for \"{$category->name}\" set to: {$qName}.");
    }

    public function toggleStatus(OfferingCategory $category)
    {
        $category->update(['is_active' => ! $category->is_active]);
        $label = $category->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Category \"{$category->name}\" {$label}.");
    }

    public function destroy(OfferingCategory $category)
    {
        if ($category->offerings()->exists()) {
            return back()->with('error', "Cannot delete \"{$category->name}\" — it has offerings attached.");
        }

        $name = $category->name;
        $category->delete();
        return back()->with('success', "Category \"{$name}\" deleted.");
    }
}
