<?php

namespace App\Http\Controllers\Web\Partner;

use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\OfferingCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfferingController extends Controller
{
    private array $usStates = [
        'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA',
        'HI','ID','IL','IN','IA','KS','KY','LA','ME','MD',
        'MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
        'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC',
        'SD','TN','TX','UT','VT','VA','WA','WV','WI','WY',
    ];

    private function partner() { return Auth::user()->partner; }

    public function index(Request $request)
    {
        $offerings = $this->partner()->offerings()->with('category')
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->active !== null && $request->active !== '', fn($q) => $q->where('is_active', (bool) $request->active))
            ->latest()->paginate(20)->withQueryString();

        return view('partner.offerings.index', compact('offerings'));
    }

    public function create()
    {
        $usStates   = $this->usStates;
        $categories = OfferingCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('partner.offerings.create', compact('usStates', 'categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'internal_name'           => 'nullable|string|max:255',
            'type'                    => 'required|in:medication,compound,supply',
            'category_id'             => 'nullable|exists:offering_categories,id',
            'description'             => 'nullable|string',
            'sku'                     => 'nullable|string|max:100',
            'price'                   => 'nullable|numeric|min:0',
            'compound_formula'        => 'required|string',
            'refills'                 => 'required|integer|min:0',
            'quantity'                => 'required|numeric|min:0',
            'days_supply'             => 'nullable|integer|min:0',
            'dispense_unit'           => 'required|string|max:100',
            'days_until_dispense'     => 'nullable|integer|min:0',
            'directions'              => 'required|string',
            'pharmacy_type'           => 'required|in:boothwyn,curexa,custom',
            'pharmacy_name'           => 'nullable|string|max:255',
            'pharmacy_notes'          => 'nullable|string',
            'dosespot_medication_id'  => 'nullable|string|max:100',
            'boothwyn_compound_id'    => 'nullable|string|max:100',
            'available_states'        => 'nullable|array',
            'available_states.*'      => 'string|size:2',
            'is_active'               => 'boolean',
            'is_controlled_substance' => 'boolean',
        ]);

        $data['is_active']               = $request->boolean('is_active');
        $data['is_controlled_substance'] = $request->boolean('is_controlled_substance');
        $data['category_id']             = $request->input('category_id') ?: null;
        $data['approval_status']         = 'pending';

        $offering = $this->partner()->offerings()->create($data);

        return redirect()->route('partner.offerings.show', $offering->id)
            ->with('success', "Offering \"{$offering->name}\" created and submitted for admin approval.");
    }

    public function show(int $id)
    {
        $offering   = $this->partner()->offerings()->with(['category', 'questionnaires'])->findOrFail($id);
        $usStates   = $this->usStates;
        $categories = OfferingCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('partner.offerings.show', compact('offering', 'usStates', 'categories'));
    }

    public function update(Request $request, int $id)
    {
        $offering = $this->partner()->offerings()->findOrFail($id);

        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'internal_name'           => 'nullable|string|max:255',
            'category_id'             => 'nullable|exists:offering_categories,id',
            'description'             => 'nullable|string',
            'price'                   => 'nullable|numeric|min:0',
            'compound_formula'        => 'required|string',
            'refills'                 => 'required|integer|min:0',
            'quantity'                => 'required|numeric|min:0',
            'days_supply'             => 'nullable|integer|min:0',
            'dispense_unit'           => 'required|string|max:100',
            'days_until_dispense'     => 'nullable|integer|min:0',
            'directions'              => 'required|string',
            'pharmacy_type'           => 'required|in:boothwyn,curexa,custom',
            'pharmacy_name'           => 'nullable|string|max:255',
            'pharmacy_notes'          => 'nullable|string',
            'dosespot_medication_id'  => 'nullable|string|max:100',
            'boothwyn_compound_id'    => 'nullable|string|max:100',
            'available_states'        => 'nullable|array',
            'is_active'               => 'boolean',
            'is_controlled_substance' => 'boolean',
        ]);

        $data['is_active']               = $request->boolean('is_active');
        $data['is_controlled_substance'] = $request->boolean('is_controlled_substance');
        $data['category_id']             = $request->input('category_id') ?: null;

        // Re-submit for review when partner edits a rejected offering
        if ($offering->approval_status === 'rejected') {
            $data['approval_status'] = 'pending';
            $data['approved_by']     = null;
            $data['approved_at']     = null;
            $data['rejection_note']  = null;
        }

        $offering->update($data);

        $message = $offering->approval_status === 'pending'
            ? 'Offering updated and re-submitted for admin approval.'
            : 'Offering updated.';

        return back()->with('success', $message);
    }

    public function toggleStatus(int $id)
    {
        $offering = $this->partner()->offerings()->findOrFail($id);
        $offering->update(['is_active' => !$offering->is_active]);

        return back()->with('success', "\"{$offering->name}\" marked " . ($offering->is_active ? 'active' : 'inactive') . '.');
    }

    public function destroy(int $id)
    {
        $offering = $this->partner()->offerings()->findOrFail($id);
        $offering->delete();

        return redirect()->route('partner.offerings.index')
            ->with('success', 'Offering deleted.');
    }
}
