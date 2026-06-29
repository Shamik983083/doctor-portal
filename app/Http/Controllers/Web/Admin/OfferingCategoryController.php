<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfferingCategory;
use Illuminate\Http\Request;

class OfferingCategoryController extends Controller
{
    public function index()
    {
        $categories = OfferingCategory::withCount('offerings')->latest()->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:150|unique:offering_categories,name',
            'description' => 'nullable|string|max:1000',
        ]);

        $data['is_active'] = true;
        OfferingCategory::create($data);

        return back()->with('success', "Category \"{$data['name']}\" created.");
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
